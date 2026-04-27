<?php

namespace App\Services\Network;

use App\Jobs\SyncPppoeMonitorJob;
use App\Models\Router;
use Throwable;

class RouterSyncService
{
    /**
     * @return array{success:bool,message:string,data:array<string,mixed>,meta:array<string,mixed>}
     */
    public function syncPppoe(Router $router): array
    {
        try {
            $chunkSize = max(1, (int) config('automation.monitoring.pppoe.chunk', 200));
            SyncPppoeMonitorJob::dispatch($router->id, $chunkSize);

            return [
                'success' => true,
                'message' => 'Sync PPPoE dijadwalkan ke queue monitoring.',
                'data' => [
                    'router_id' => $router->id,
                    'router_code' => $router->router_code,
                    'router_name' => $router->router_name,
                    'sync_type' => 'pppoe',
                    'execution' => 'queued',
                    'queue' => (string) config('automation.queues.monitoring', 'monitoring'),
                    'chunk_size' => $chunkSize,
                    'job' => SyncPppoeMonitorJob::class,
                ],
                'meta' => [
                    'placeholder' => false,
                ],
            ];
        } catch (Throwable $throwable) {
            report($throwable);

            return [
                'success' => false,
                'message' => 'Sync PPPoE gagal dijadwalkan.',
                'data' => [
                    'router_id' => $router->id,
                    'router_code' => $router->router_code,
                    'router_name' => $router->router_name,
                    'sync_type' => 'pppoe',
                    'execution' => 'failed',
                    'error' => $throwable->getMessage(),
                ],
                'meta' => [
                    'placeholder' => false,
                ],
            ];
        }
    }

    /**
     * @return array{success:bool,message:string,data:array<string,mixed>,meta:array<string,mixed>}
     */
    public function syncStatic(Router $router): array
    {
        return $this->placeholderResponse($router, 'static');
    }

    /**
     * @return array{success:bool,message:string,data:array<string,mixed>,meta:array<string,mixed>}
     */
    public function syncAddressList(Router $router): array
    {
        return $this->placeholderResponse($router, 'address_list');
    }

    /**
     * @return array{success:bool,message:string,data:array<string,mixed>,meta:array<string,mixed>}
     */
    public function syncAll(Router $router): array
    {
        $pppoe = $this->syncPppoe($router);
        $static = $this->syncStatic($router);
        $addressList = $this->syncAddressList($router);

        $allSuccess = (bool) ($pppoe['success'] ?? false)
            && (bool) ($static['success'] ?? false)
            && (bool) ($addressList['success'] ?? false);

        return [
            'success' => $allSuccess,
            'message' => $allSuccess
                ? 'Sync All diproses.'
                : 'Sync All selesai dengan sebagian operasi belum aktif.',
            'data' => [
                'router_id' => $router->id,
                'router_code' => $router->router_code,
                'router_name' => $router->router_name,
                'sync_type' => 'all',
                'results' => [
                    'pppoe' => $pppoe,
                    'static' => $static,
                    'address_list' => $addressList,
                ],
            ],
            'meta' => [
                'placeholder' => (bool) ($static['meta']['placeholder'] ?? true)
                    || (bool) ($addressList['meta']['placeholder'] ?? true),
            ],
        ];
    }

    /**
     * @return array{success:bool,message:string,data:array<string,mixed>,meta:array<string,mixed>}
     */
    private function placeholderResponse(Router $router, string $type): array
    {
        return [
            'success' => true,
            'message' => 'Router sync placeholder. Backend sync belum aktif.',
            'data' => [
                'router_id' => $router->id,
                'router_code' => $router->router_code,
                'router_name' => $router->router_name,
                'sync_type' => $type,
                'execution' => 'placeholder',
            ],
            'meta' => [
                'placeholder' => true,
            ],
        ];
    }
}
