<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Router;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PppoeImportController extends Controller
{
    public function __construct(
        private readonly MikrotikApiClientFactory $factory,
    ) {}

    public function candidates(Request $request): JsonResponse
    {
        $router = Router::where('router_code', 'RTR-CCR-WARNET')->firstOrFail();

        $client = $this->factory->forRouter($router);
        $secrets = $client->print('/ppp/secret', [
            'name',
            'password',
            'profile',
            'comment',
            'service',
        ]);

        // Filter only PPPoE services
        $secrets = array_filter(
            $secrets,
            fn (array $s): bool => ($s['service'] ?? '') === 'pppoe',
        );

        // Get usernames already in DB
        $existingUsernames = Service::whereNotNull('pppoe_username')
            ->where('pppoe_username', '!=', '')
            ->pluck('pppoe_username')
            ->map(fn ($u) => strtolower(trim($u)))
            ->toArray();

        // Filter: only candidates not yet imported
        $candidates = array_values(array_filter(
            $secrets,
            fn (array $s): bool => !in_array(
                strtolower(trim($s['name'] ?? '')),
                $existingUsernames,
                true,
            ),
        ));

        // Format response
        $result = array_map(
            fn (array $s): array => [
                'username' => $s['name'] ?? '',
                'password' => $s['password'] ?? '',
                'profile' => $s['profile'] ?? '',
                'comment' => $s['comment'] ?? '',
            ],
            $candidates,
        );

        return response()->json([
            'data' => $result,
            'total' => count($result),
            'router' => [
                'id' => $router->id,
                'name' => $router->name,
                'code' => $router->router_code,
            ],
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'usernames' => ['required', 'array', 'min:1', 'max:500'],
            'usernames.*' => ['required', 'string', 'max:100'],
        ]);

        $router = Router::where('router_code', 'RTR-CCR-WARNET')->firstOrFail();

        // Re-fetch secrets from Mikrotik for validation
        $client = $this->factory->forRouter($router);
        $secrets = $client->print('/ppp/secret', ['name', 'password', 'profile', 'comment']);
        $secretMap = collect($secrets)->keyBy('name');

        // Generate all customer codes first to avoid race conditions
        $lastCode = Customer::query()
            ->lockForUpdate()
            ->orderBy('id', 'desc')
            ->value('customer_code');
        $lastNumber = $lastCode
            ? (int) preg_replace('/[^0-9]/', '', $lastCode)
            : 0;

        $imported = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($request, $router, $secretMap, &$imported, &$skipped, &$errors, &$lastNumber): void {
            foreach ($request->usernames as $username) {
                $username = trim($username);

                // Skip if already in DB
                $alreadyExists = Service::where('pppoe_username', $username)->exists();
                if ($alreadyExists) {
                    $skipped++;
                    continue;
                }

                $secret = $secretMap->get($username);
                if (!$secret) {
                    $errors[] = "Username '{$username}' tidak ditemukan di Mikrotik.";
                    continue;
                }

                try {
                    // Generate sequential customer_code
                    $lastNumber++;
                    $customerCode = 'CUST-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);

                    // Create new customer
                    $customer = Customer::create([
                        'customer_code' => $customerCode,
                        'full_name' => $username,
                        'status' => 'active',
                        'notes' => 'Diimport dari PPPoE secret Mikrotik ' . now()->toDateString(),
                    ]);

                    // Create service with default values
                    Service::create([
                        'customer_id' => $customer->id,
                        'router_id' => $router->id,
                        'pppoe_username' => $username,
                        'pppoe_password' => $secret['password'] ?? '',
                        'ip_count' => 1,
                        'monthly_price' => 0,
                        'billing_day' => 1,
                        'status' => 'active',
                        'monitor_pppoe_username' => $username,
                    ]);

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Gagal import '{$username}': " . $e->getMessage();
                    Log::error('PPPoE import failed', [
                        'username' => $username,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        return response()->json([
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => "Berhasil import {$imported} customer. Skip {$skipped}. Error: " . count($errors),
        ]);
    }
}