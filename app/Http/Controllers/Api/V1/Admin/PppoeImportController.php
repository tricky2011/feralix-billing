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
            'disabled',
        ]);

        $client->disconnect();

        // Filter: hanya PPPoE secrets yang aktif
        // ROS v6: service='pppoe'
        // ROS v7: service='any' (default) atau 'pppoe'
        // Exclude: l2tp, pptp, sstp, ovpn, disabled=yes
        $secrets = array_filter(
            $secrets,
            static function (array $s): bool {
                // Skip disabled secrets
                if (in_array(strtolower($s['disabled'] ?? 'false'), ['true', 'yes', '1'], true)) {
                    return false;
                }

                $service = strtolower(trim($s['service'] ?? 'any'));

                // Exclude non-PPPoE services
                if (in_array($service, ['l2tp', 'pptp', 'sstp', 'ovpn'], true)) {
                    return false;
                }

                // Accept: pppoe, any, '' (ROS v6 dan v7)

                // Accept: pppoe, any, '' (empty = any)
                return true;
            },
        );

        // Get usernames already imported for THIS router only
        $existingThisRouter = Service::where('router_id', $router->id)
            ->where(function ($q): void {
                $q->whereNotNull('pppoe_username')->where('pppoe_username', '!=', '');
            })
            ->pluck('pppoe_username')
            ->map(static fn (string $u): string => strtolower(trim($u)))
            ->flip()
            ->all();

        // Filter: only candidates not yet imported for this router
        $candidates = array_values(array_filter(
            $secrets,
            static fn (array $s): bool => !isset($existingThisRouter[strtolower(trim($s['name'] ?? ''))]),
        ));

        // Format response
        $result = array_map(
            static fn (array $s): array => [
                'username' => trim($s['name'] ?? ''),
                'password' => $s['password'] ?? '',
                'profile'  => $s['profile'] ?? 'default',
                'comment'  => $s['comment'] ?? '',
                'service'  => $s['service'] ?? 'any',
            ],
            $candidates,
        );

        return response()->json([
            'data' => $result,
            'message' => 'Candidates retrieved successfully.',
            'total'     => count($result),
            'total_all' => count($secrets),
            'router'    => [
                'id'   => $router->id,
                'name' => $router->router_name ?? $router->name,
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

                // Skip if already imported for this router
                $alreadyExists = Service::where('router_id', $router->id)
                    ->where('pppoe_username', $username)
                    ->exists();
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

                    // Generate unique service_code from username + router prefix to avoid unique constraint collision across routers
                    $routerCode = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $router->router_code ?? $router->router_name ?? $router->id));
                    $serviceCode = 'SVC-' . $routerCode . '-' . strtoupper(preg_replace('/[^A-Z0-9]/i', '', $username));

                    // If service_code already exists (duplicate from previous import), append router suffix to make unique
                    if (Service::where('service_code', $serviceCode)->exists()) {
                        $serviceCode .= '-' . $router->id;
                    }

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