<?php

namespace App\Services\Provisioning;

use App\Enums\ServiceAccessMode;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceIsolationTargetType;
use App\Enums\VidType;
use App\Models\Service;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceIsolationTargetResolver
{
    /**
     * @return array{
     *     target_type:string,
     *     target_identifier:string,
     *     target_subnet:string|null,
     *     address_list_name:string|null,
     *     target_payload:array<string, mixed>|null
     * }
     */
    public function resolve(Service $service, array $overrides = []): array
    {
        if ($service->vid !== null && $service->vid->vid_type !== VidType::CustomerInternet) {
            $vidNumber = $service->vid->vid;
            throw ValidationException::withMessages([
                'vid' => "VID {$vidNumber} adalah VID monitoring/PPPoE server dan tidak boleh diisolasi.",
            ]);
        }

        $targetType = isset($overrides['target_type'])
            ? ServiceIsolationTargetType::from((string) $overrides['target_type'])
            : $this->inferTargetType($service);

        return match ($targetType) {
            ServiceIsolationTargetType::Pppoe => $this->resolvePppoeTarget($service, $overrides),
            ServiceIsolationTargetType::Static => $this->resolveStaticTarget($service, $overrides),
            ServiceIsolationTargetType::Subnet => $this->resolveSubnetTarget($service, $overrides),
        };
    }

    public function inferTargetType(Service $service): ServiceIsolationTargetType
    {
        $accessMode = $service->resolvedAccessMode();

        if (
            $accessMode === ServiceAccessMode::Pppoe ||
            ($service->isolation_method instanceof ServiceIsolationMethod && $service->isolation_method->usesPppProfile())
        ) {
            return ServiceIsolationTargetType::Pppoe;
        }

        if (
            $accessMode === ServiceAccessMode::Static ||
            ($service->isolation_method instanceof ServiceIsolationMethod && $service->isolation_method->usesQueue())
        ) {
            return ServiceIsolationTargetType::Static;
        }

        return ServiceIsolationTargetType::Subnet;
    }

    /**
     * @return array{
     *     target_type:string,
     *     target_identifier:string,
     *     target_subnet:string|null,
     *     address_list_name:string|null,
     *     target_payload:array<string, mixed>|null
     * }
     */
    private function resolveSubnetTarget(Service $service, array $overrides): array
    {
        $targetSubnet = $this->normalizeNullableString($overrides['target_subnet'] ?? $service->isolationTargetSubnet());

        if ($targetSubnet === null) {
            throw ValidationException::withMessages([
                'target_subnet' => 'The selected service does not have a subnet available for isolation.',
            ]);
        }

        return [
            'target_type' => ServiceIsolationTargetType::Subnet->value,
            'target_identifier' => $this->normalizeNullableString($overrides['target_identifier'] ?? $targetSubnet) ?? $targetSubnet,
            'target_subnet' => $targetSubnet,
            'address_list_name' => $this->resolveAddressListName($service, $overrides),
            'target_payload' => $this->filterPayload([
                'access_mode' => $service->resolvedAccessMode()->value,
                'isolation_method' => $service->isolation_method?->value,
            ]),
        ];
    }

    /**
     * @return array{
     *     target_type:string,
     *     target_identifier:string,
     *     target_subnet:string|null,
     *     address_list_name:string|null,
     *     target_payload:array<string, mixed>|null
     * }
     */
    private function resolvePppoeTarget(Service $service, array $overrides): array
    {
        $pppoeUsername = $this->normalizeNullableString(
            $overrides['target_identifier']
                ?? $service->operationalPppoeUsername()
                ?? $service->routerOperationStatus?->pppoe_username
        );

        if ($pppoeUsername === null) {
            throw ValidationException::withMessages([
                'target_identifier' => 'The selected service does not have a PPPoE username available for isolation.',
            ]);
        }

        return [
            'target_type' => ServiceIsolationTargetType::Pppoe->value,
            'target_identifier' => Str::lower($pppoeUsername),
            'target_subnet' => $this->normalizeNullableString($overrides['target_subnet'] ?? $service->isolationTargetSubnet()),
            'address_list_name' => $this->resolveAddressListName($service, $overrides),
            'target_payload' => $this->filterPayload([
                'access_mode' => ServiceAccessMode::Pppoe->value,
                'isolation_method' => $service->isolation_method?->value,
                'pppoe_isolation_profile' => $this->normalizeNullableString(
                    $service->pppoe_isolation_profile
                        ?? $service->routerOperationStatus?->ppp_secret_profile
                ),
                'ppp_active_address' => $this->normalizeNullableString($service->routerOperationStatus?->ppp_active_address),
                'ppp_active_caller_id' => $this->normalizeNullableString($service->routerOperationStatus?->ppp_active_caller_id),
            ]),
        ];
    }

    /**
     * @return array{
     *     target_type:string,
     *     target_identifier:string,
     *     target_subnet:string|null,
     *     address_list_name:string|null,
     *     target_payload:array<string, mixed>|null
     * }
     */
    private function resolveStaticTarget(Service $service, array $overrides): array
    {
        $targetIdentifier = $this->normalizeNullableString(
            $overrides['target_identifier']
                ?? $service->operationalStaticIpAddress()
                ?? $service->routerOperationStatus?->static_ip_address
                ?? $overrides['target_subnet']
                ?? $service->isolationTargetSubnet()
        );

        if ($targetIdentifier === null) {
            throw ValidationException::withMessages([
                'target_identifier' => 'The selected service does not have a static address or binding target available for isolation.',
            ]);
        }

        return [
            'target_type' => ServiceIsolationTargetType::Static->value,
            'target_identifier' => $targetIdentifier,
            'target_subnet' => $this->normalizeNullableString($overrides['target_subnet'] ?? $service->isolationTargetSubnet()),
            'address_list_name' => $this->resolveAddressListName($service, $overrides),
            'target_payload' => $this->filterPayload([
                'access_mode' => ServiceAccessMode::Static->value,
                'isolation_method' => $service->isolation_method?->value,
                'static_ip_address' => $this->normalizeNullableString(
                    $service->operationalStaticIpAddress() ?? $service->routerOperationStatus?->static_ip_address
                ),
                'static_mac_address' => $this->normalizeNullableString(
                    $service->static_mac_address ?? $service->routerOperationStatus?->static_mac_address
                ),
                'static_queue_name' => $this->normalizeNullableString($service->static_queue_name),
            ]),
        ];
    }

    private function resolveAddressListName(Service $service, array $overrides): ?string
    {
        $addressListName = $this->normalizeNullableString(
            $overrides['address_list_name']
                ?? config('mikrotik.isolation.address_list_name')
                ?? $service->address_list_name
        );

        return $addressListName ?? Str::limit('ISOLIR_CUSTOMER', 100, '');
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== ''
            ? $value
            : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function filterPayload(array $payload): ?array
    {
        $filtered = array_filter($payload, static function (mixed $value): bool {
            if (is_array($value)) {
                return $value !== [];
            }

            return $value !== null && $value !== '';
        });

        return $filtered !== []
            ? $filtered
            : null;
    }
}
