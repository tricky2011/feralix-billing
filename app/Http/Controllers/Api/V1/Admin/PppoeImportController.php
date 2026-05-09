<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Package;
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
        $request->validate(['router_id' => 'required|exists:routers,id']);
        $router = Router::findOrFail((int) $request->router_id);

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
            ->where('router_id', $router->id)
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

        $request->validate(['router_id' => 'required|exists:routers,id']);
        $router = Router::findOrFail((int) $request->router_id);

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

        // Get or create default package for imported services
        $defaultPackage = Package::firstOrCreate(
            ['package_name' => 'Import Default'],
            ['monthly_price' => 0, 'is_active' => true],
        );

        $imported = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($request, $router, $secretMap, &$imported, &$skipped, &$errors, &$lastNumber, $defaultPackage): void {
            foreach ($request->usernames as $username) {
                $username = trim($username);

                // Skip if already in DB
                $alreadyExists = Service::where('pppoe_username', $username)->where('router_id', $router->id)->exists();
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

                    // Generate unique service_code from username
                    $serviceCode = 'SVC-' . strtoupper(preg_replace('/[^A-Z0-9]/i', '', $username));

                    // Create new customer
                    $customer = Customer::create([
                        'customer_code' => $customerCode,
                        'full_name' => trim($username),
                        'phone' => '',
                        'address' => '',
                        'status' => 'active',
                        'notes' => 'Diimport dari PPPoE Mikrotik ' . now()->toDateString(),
                    ]);

                    // Create service with all NOT NULL fields filled
                    Service::create([
                        'customer_id' => $customer->id,
                        'package_id' => $defaultPackage->id,
                        'router_id' => $router->id,
                        'service_code' => $serviceCode,
                        'monitor_vid' => 0,
                        'monitor_pppoe_username' => $username,
                        'monitor_pppoe_password' => '',
                        'internet_vid' => 0,
                        'subnet_cidr' => '',
                        'dhcp_pool_start' => '',
                        'dhcp_pool_end' => '',
                        'ip_pool_count' => 5,
                        'rate_limit_mbps' => 10,
                        'ip_count' => 1,
                        'access_mode' => 'pppoe',
                        'pppoe_username' => $username,
                        'pppoe_password' => $secret['password'] ?? '',
                        'isolation_method' => 'address_list',
                        'billing_status' => 'pending',
                        'network_status' => 'provisioning',
                        'overall_status' => 'provisioning',
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