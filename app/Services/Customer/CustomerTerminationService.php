<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Router;
use App\Services\Mikrotik\IpPoolSyncService;
use App\Services\Mikrotik\MikrotikPppoeSecretService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerTerminationService
{
    public function __construct(
        private readonly MikrotikPppoeSecretService $pppoeSecretService,
        private readonly IpPoolSyncService $ipPoolSyncService,
    ) {}

    /**
     * Terminate a customer:
     * 1. Remove PPPoE from Mikrotik
     * 2. Sync IP pools (VID back to Available)
     * 3. Mark unpaid invoices as paid (write-off)
     * 4. Terminate all services
     * 5. Hard delete customer (cascade to services and invoices)
     */
    public function terminate(Customer $customer): array
    {
        $log = [];
        $customer->load(['services', 'services.router']);

        // STEP 1: Remove PPPoE from Mikrotik
        foreach ($customer->services as $service) {
            if ($service->pppoe_username && $service->router_id) {
                $router = Router::find($service->router_id);
                if ($router) {
                    $result = $this->pppoeSecretService->removeSecret(
                        router: $router,
                        username: $service->pppoe_username,
                    );
                    $log[] = 'PPPoE ' . $service->pppoe_username . ': ' . $result['message'];
                }
            }
        }

        // STEP 2: Sync IP pools (VID back to Available)
        $routerIds = $customer->services->pluck('router_id')->filter()->unique();
        foreach ($routerIds as $routerId) {
            $router = Router::find($routerId);
            if ($router) {
                try {
                    $this->ipPoolSyncService->syncFromMikrotik($router);
                    $log[] = 'IP Pool synced for router ' . $router->router_code;
                } catch (\Throwable $e) {
                    Log::warning('Failed to sync IP pool on termination', [
                        'router_id' => $routerId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        DB::transaction(function () use ($customer, &$log): void {
            // STEP 3: Mark unpaid invoices as paid (write-off)
            $unpaidCount = Invoice::where('customer_id', $customer->id)
                ->whereNotIn('payment_status', ['paid', 'canceled'])
                ->update([
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                    'status_changed_at' => now(),
                ]);
            $log[] = $unpaidCount . ' invoice(s) marked as paid (write-off)';

            // STEP 4: Terminate all services
            foreach ($customer->services as $service) {
                $service->update([
                    'overall_status' => 'terminated',
                    'billing_status' => 'terminated',
                    'network_status' => 'inactive',
                ]);
            }
            $log[] = $customer->services->count() . ' service(s) terminated';

            // STEP 5: Hard delete customer (cascade)
            // Hapus relasi dulu karena ada FK constraint
            Invoice::where('customer_id', $customer->id)->forceDelete();
            $customer->services()->delete();
            $customer->delete();
            $log[] = 'Customer deleted: ' . $customer->customer_code;
        });

        return $log;
    }
}