<?php

namespace App\Services\Provisioning;

use App\Enums\ServiceAccessMode;
use App\Models\Service;
use App\Models\ServiceIsolation;
use Illuminate\Support\Str;
use RuntimeException;

class ServiceIsolationRouterTargetResolver
{
    /**
     * @return array{
     *     address_list_name:string,
     *     target_address:string,
     *     comment:string
     * }
     */
    public function resolve(ServiceIsolation $serviceIsolation): array
    {
        $service = $serviceIsolation->service;

        if ($service === null) {
            throw new RuntimeException('Service isolation does not have a related service.');
        }

        return [
            'address_list_name' => $this->resolveAddressListName($serviceIsolation, $service),
            'target_address' => $this->resolveTargetAddress($serviceIsolation, $service),
            'comment' => $this->buildComment($serviceIsolation, $service),
        ];
    }

    private function resolveAddressListName(ServiceIsolation $serviceIsolation, Service $service): string
    {
        $configuredName = trim((string) config('mikrotik.isolation.address_list_name', 'ISOLIR_CUSTOMER'));
        $isolationName = trim((string) ($serviceIsolation->address_list_name ?? ''));
        $serviceName = trim((string) ($service->address_list_name ?? ''));

        $resolvedName = $isolationName !== ''
            ? $isolationName
            : ($configuredName !== '' ? $configuredName : ($serviceName !== '' ? $serviceName : 'ISOLIR_CUSTOMER'));

        return Str::limit($resolvedName, 100, '');
    }

    private function resolveTargetAddress(ServiceIsolation $serviceIsolation, Service $service): string
    {
        $candidates = [];

        if ($service->resolvedAccessMode() === ServiceAccessMode::Static) {
            $candidates[] = $service->operationalStaticIpAddress();
            $candidates[] = $service->routerOperationStatus?->static_ip_address;
            $candidates[] = data_get($serviceIsolation->target_payload, 'static_ip_address');
        }

        $candidates[] = $service->dhcp_pool_start;
        $candidates[] = $service->vid?->pool_start_ip;
        $candidates[] = $serviceIsolation->target_identifier;

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $candidate = trim($candidate);

            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        throw new RuntimeException('No customer IP could be resolved from the service DHCP pool or target payload.');
    }

    private function buildComment(ServiceIsolation $serviceIsolation, Service $service): string
    {
        $parts = [
            'feralix-billing',
            $serviceIsolation->status?->value ?? 'pending',
            'service:'.$service->service_code,
            'isolation:'.$serviceIsolation->id,
            'customer:'.$service->customer_id,
        ];

        return Str::limit(implode(' ', $parts), 200, '');
    }
}
