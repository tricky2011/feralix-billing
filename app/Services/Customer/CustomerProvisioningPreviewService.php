<?php

namespace App\Services\Customer;

use App\Models\Service;
use App\Models\Vid;

class CustomerProvisioningPreviewService
{
    public function preview(array $payload): array
    {
        $source = $this->resolveSource($payload);
        $overlappingServices = $this->overlappingServices(
            $source['router_id'],
            $source['subnet_cidr'],
            $payload['exclude_service_id'] ?? $source['service_id'],
        );

        $warnings = [];

        if ($source['dhcp_pool_start'] === null || $source['dhcp_pool_end'] === null) {
            $warnings[] = 'DHCP pool is incomplete; remote IP suggestion cannot be calculated accurately.';
        }

        if ($overlappingServices !== []) {
            $warnings[] = 'Another service already uses the same router/subnet allocation.';
        }

        $remoteIpSuggestion = $overlappingServices === []
            ? $this->suggestRemoteIp(
                $source['gateway_ip'],
                $source['dhcp_pool_start'],
                $source['dhcp_pool_end'],
            )
            : null;

        return [
            'source' => $source['source'],
            'router_id' => $source['router_id'],
            'service_id' => $source['service_id'],
            'vid_id' => $source['vid_id'],
            'internet_vid' => $source['internet_vid'],
            'subnet_cidr' => $source['subnet_cidr'],
            'gateway_ip' => $source['gateway_ip'],
            'dhcp_pool_start' => $source['dhcp_pool_start'],
            'dhcp_pool_end' => $source['dhcp_pool_end'],
            'remote_ip_suggestion' => $remoteIpSuggestion,
            'pool_capacity' => $this->poolCapacity($source['dhcp_pool_start'], $source['dhcp_pool_end']),
            'warnings' => $warnings,
            'overlapping_services' => $overlappingServices,
        ];
    }

    private function resolveSource(array $payload): array
    {
        if (($payload['service_id'] ?? null) !== null) {
            $service = Service::query()
                ->select([
                    'id',
                    'router_id',
                    'vid_id',
                    'internet_vid',
                    'subnet_cidr',
                    'gateway_ip',
                    'dhcp_pool_start',
                    'dhcp_pool_end',
                ])
                ->findOrFail((int) $payload['service_id']);

            return [
                'source' => 'service',
                'service_id' => $service->id,
                'router_id' => $service->router_id,
                'vid_id' => $service->vid_id,
                'internet_vid' => $service->internet_vid,
                'subnet_cidr' => $service->subnet_cidr,
                'gateway_ip' => $service->gateway_ip,
                'dhcp_pool_start' => $service->dhcp_pool_start,
                'dhcp_pool_end' => $service->dhcp_pool_end,
            ];
        }

        if (($payload['vid_id'] ?? null) !== null) {
            $vid = Vid::query()
                ->select([
                    'id',
                    'router_id',
                    'vid',
                    'subnet_cidr',
                    'gateway_ip',
                    'pool_start_ip',
                    'pool_end_ip',
                ])
                ->findOrFail((int) $payload['vid_id']);

            return [
                'source' => 'vid',
                'service_id' => null,
                'router_id' => $vid->router_id,
                'vid_id' => $vid->id,
                'internet_vid' => $vid->vid,
                'subnet_cidr' => $vid->subnet_cidr,
                'gateway_ip' => $vid->gateway_ip,
                'dhcp_pool_start' => $vid->pool_start_ip,
                'dhcp_pool_end' => $vid->pool_end_ip,
            ];
        }

        return [
            'source' => 'manual',
            'service_id' => null,
            'router_id' => $payload['router_id'] ?? null,
            'vid_id' => null,
            'internet_vid' => null,
            'subnet_cidr' => $payload['subnet_cidr'] ?? null,
            'gateway_ip' => $payload['gateway_ip'] ?? null,
            'dhcp_pool_start' => $payload['dhcp_pool_start'] ?? null,
            'dhcp_pool_end' => $payload['dhcp_pool_end'] ?? null,
        ];
    }

    private function suggestRemoteIp(?string $gatewayIp, ?string $poolStart, ?string $poolEnd): ?string
    {
        if ($poolStart === null || $poolEnd === null) {
            return null;
        }

        $start = ip2long($poolStart);
        $end = ip2long($poolEnd);
        $gateway = $gatewayIp !== null ? ip2long($gatewayIp) : false;

        if ($start === false || $end === false || $start > $end) {
            return null;
        }

        for ($candidate = $start; $candidate <= $end; $candidate++) {
            if ($gateway !== false && $candidate === $gateway) {
                continue;
            }

            return long2ip($candidate);
        }

        return null;
    }

    private function poolCapacity(?string $poolStart, ?string $poolEnd): ?int
    {
        if ($poolStart === null || $poolEnd === null) {
            return null;
        }

        $start = ip2long($poolStart);
        $end = ip2long($poolEnd);

        if ($start === false || $end === false || $start > $end) {
            return null;
        }

        return (int) (($end - $start) + 1);
    }

    private function overlappingServices(?int $routerId, ?string $subnetCidr, ?int $excludeServiceId): array
    {
        if ($routerId === null || $subnetCidr === null || $subnetCidr === '') {
            return [];
        }

        return Service::query()
            ->select(['id', 'customer_id', 'router_id', 'service_code', 'subnet_cidr'])
            ->where('router_id', $routerId)
            ->where('subnet_cidr', $subnetCidr)
            ->when($excludeServiceId !== null, fn ($query) => $query->whereKeyNot($excludeServiceId))
            ->orderBy('id')
            ->get()
            ->map(static fn (Service $service): array => [
                'id' => $service->id,
                'customer_id' => $service->customer_id,
                'service_code' => $service->service_code,
                'subnet_cidr' => $service->subnet_cidr,
            ])
            ->all();
    }
}
