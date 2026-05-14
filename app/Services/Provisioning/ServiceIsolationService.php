<?php

namespace App\Services\Provisioning;

use App\Enums\ServiceIsolationStatus;
use App\Enums\ServiceIsolationType;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceIsolation;
use App\Models\ServiceRouterOperationStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceIsolationService
{
    public function __construct(
        private readonly ServiceOverallStateManager $overallStateManager,
        private readonly ServiceIsolationTargetResolver $targetResolver,
        private readonly ServiceIsolationRouterJobDispatcher $routerJobDispatcher,
    ) {}

    private const RELATIONS = [
        'service:id,customer_id,router_id,service_code,access_mode,pppoe_username,pppoe_isolation_profile,static_ip_address,static_mac_address,static_queue_name,subnet_cidr,address_list_name,billing_status,network_status,overall_status',
        'service.routerOperationStatus:id,service_id,router_id,access_mode,isolation_target_type,pppoe_username,static_ip_address,queue_name,queue_found,address_list_name,address_list_found,open_isolation_id,isolation_applied,isolation_detected_via,last_synced_at,isolation_verified_at',
        'router:id,router_code,router_name',
        'invoice:id,service_id,invoice_number,due_date,payment_status',
        'creator:id,name,email',
    ];

    public function createIsolationRecord(array $payload): ServiceIsolation
    {
        return DB::transaction(function () use ($payload): ServiceIsolation {
            $service = Service::query()
                ->with('vid:id,vid_type,subnet_cidr')
                ->lockForUpdate()
                ->findOrFail((int) $payload['service_id']);

            $routerId = (int) ($payload['router_id'] ?? $service->router_id);
            $this->ensureRouterMatchesService($service, $routerId);

            $invoice = isset($payload['invoice_id'])
                ? Invoice::query()->lockForUpdate()->findOrFail((int) $payload['invoice_id'])
                : null;

            $requestedType = isset($payload['isolation_type'])
                ? ServiceIsolationType::from($payload['isolation_type'])
                : null;

            $this->ensureInvoiceMatchesService($invoice, $service, $requestedType);
            $this->ensureNoOpenIsolation($service);

            $target = $this->targetResolver->resolve($service, $payload);

            $isolation = ServiceIsolation::query()->create([
                'service_id' => $service->id,
                'router_id' => $routerId,
                'invoice_id' => $invoice?->id,
                'isolation_type' => $this->resolveIsolationType($requestedType, $invoice)->value,
                'target_type' => $target['target_type'],
                'address_list_name' => $target['address_list_name'] ?? $this->resolveAddressListName($service),
                'target_subnet' => $target['target_subnet'],
                'target_identifier' => $target['target_identifier'],
                'target_payload' => $target['target_payload'],
                'redirect_url' => isset($payload['redirect_url']) && $payload['redirect_url'] !== ''
                    ? $payload['redirect_url']
                    : $this->defaultRedirectUrl(),
                'status' => ServiceIsolationStatus::Pending->value,
                'created_by' => Auth::id(),
                'notes' => $payload['notes'] ?? null,
            ]);

            $this->syncRouterOperationIsolationState($service, $isolation, false);

            DB::afterCommit(fn () => $this->routerJobDispatcher->dispatchIsolation($isolation->id));

            return $this->loadIsolation($isolation);
        });
    }

    public function markApplied(ServiceIsolation $serviceIsolation, array $payload = []): ServiceIsolation
    {
        return DB::transaction(function () use ($serviceIsolation, $payload): ServiceIsolation {
            $isolation = ServiceIsolation::query()
                ->with('service')
                ->lockForUpdate()
                ->findOrFail($serviceIsolation->id);

            if ($isolation->status === ServiceIsolationStatus::Applied) {
                throw ValidationException::withMessages([
                    'status' => 'The isolation is already marked as applied.',
                ]);
            }

            if ($isolation->status === ServiceIsolationStatus::Released) {
                throw ValidationException::withMessages([
                    'status' => 'Released isolation records cannot be marked as applied.',
                ]);
            }

            $isolation->update([
                'status' => ServiceIsolationStatus::Applied->value,
                'isolated_at' => $payload['isolated_at'] ?? now(),
                'notes' => $this->appendNotes($isolation->notes, $payload['notes'] ?? null),
            ]);

            if ($isolation->service !== null) {
                $desiredOverallStatus = ServiceOverallStatus::Isolated;
                $effectiveOverallStatus = $this->overallStateManager->syncDesiredStatus(
                    $isolation->service,
                    $desiredOverallStatus,
                );

                $isolation->service->update([
                    'network_status' => ServiceNetworkStatus::Isolated->value,
                    'overall_status' => $effectiveOverallStatus->value,
                ]);

                $this->syncRouterOperationIsolationState($isolation->service, $isolation, true);
            }

            return $this->loadIsolation($isolation);
        });
    }

    public function markFailed(ServiceIsolation $serviceIsolation, string $reason, array $payload = []): ServiceIsolation
    {
        return DB::transaction(function () use ($serviceIsolation, $reason, $payload): ServiceIsolation {
            $isolation = ServiceIsolation::query()
                ->with('service')
                ->lockForUpdate()
                ->findOrFail($serviceIsolation->id);

            if ($isolation->status === ServiceIsolationStatus::Failed) {
                return $isolation;
            }

            if ($isolation->status === ServiceIsolationStatus::Released) {
                return $isolation;
            }

            $failureNotes = sprintf(
                '[FAILED] %s | Reason: %s',
                now()->format('Y-m-d H:i:s'),
                $reason,
            );

            $isolation->update([
                'status' => ServiceIsolationStatus::Failed->value,
                'notes' => $this->appendNotes($isolation->notes, $failureNotes),
            ]);

            if ($isolation->service !== null) {
                $this->syncRouterOperationIsolationState($isolation->service, $isolation, false);
            }

            return $this->loadIsolation($isolation);
        });
    }

    public function markReleaseFailed(ServiceIsolation $serviceIsolation, string $reason, array $payload = []): ServiceIsolation
    {
        return DB::transaction(function () use ($serviceIsolation, $reason, $payload): ServiceIsolation {
            $isolation = ServiceIsolation::query()
                ->with('service')
                ->lockForUpdate()
                ->findOrFail($serviceIsolation->id);

            if ($isolation->status === ServiceIsolationStatus::Failed) {
                return $isolation;
            }

            $failureNotes = sprintf(
                '[RELEASE_FAILED] %s | Reason: %s',
                now()->format('Y-m-d H:i:s'),
                $reason,
            );

            if ($isolation->status === ServiceIsolationStatus::ReleasePending) {
                $isolation->update([
                    'status' => ServiceIsolationStatus::Applied->value,
                    'released_at' => null,
                    'notes' => $this->appendNotes($isolation->notes, $failureNotes),
                ]);
            } elseif ($isolation->status === ServiceIsolationStatus::Released) {
                $isolation->update([
                    'status' => ServiceIsolationStatus::Applied->value,
                    'released_at' => null,
                    'notes' => $this->appendNotes($isolation->notes, $failureNotes),
                ]);
            } else {
                $isolation->update([
                    'status' => ServiceIsolationStatus::Failed->value,
                    'notes' => $this->appendNotes($isolation->notes, $failureNotes),
                ]);
            }

            if ($isolation->service !== null) {
                $this->syncRouterOperationIsolationState($isolation->service, $isolation, true);
            }

            return $this->loadIsolation($isolation);
        });
    }

    public function releaseIsolation(ServiceIsolation $serviceIsolation, array $payload = []): ServiceIsolation
    {
        return DB::transaction(function () use ($serviceIsolation, $payload): ServiceIsolation {
            $isolation = ServiceIsolation::query()
                ->with('service')
                ->lockForUpdate()
                ->findOrFail($serviceIsolation->id);

            if ($isolation->status === ServiceIsolationStatus::Released) {
                throw ValidationException::withMessages([
                    'status' => 'The isolation is already released.',
                ]);
            }

            $wasApplied = $isolation->status === ServiceIsolationStatus::Applied;

            // Status tidak langsung diubah ke Released di sini.
            // Ubah ke ReleasePending, router job akan mengupdate ke Released SETELAH sukses.
            $isolation->update([
                'status' => ServiceIsolationStatus::ReleasePending->value,
                'released_at' => $payload['released_at'] ?? now(),
                'notes' => $this->appendNotes($isolation->notes, $payload['notes'] ?? null),
            ]);

            // Jangan update service status di sini! Itu dilakukan oleh
            // ServiceIsolationRouterExecutionService setelah router job berhasil.

            DB::afterCommit(fn () => $this->routerJobDispatcher->dispatchRelease($isolation->id));

            return $this->loadIsolation($isolation);
        });
    }

    private function ensureRouterMatchesService(Service $service, int $routerId): void
    {
        if ((int) $service->router_id === $routerId) {
            return;
        }

        throw ValidationException::withMessages([
            'router_id' => 'The selected router must match the router assigned to the selected service.',
        ]);
    }

    private function ensureInvoiceMatchesService(?Invoice $invoice, Service $service, ?ServiceIsolationType $requestedType): void
    {
        if ($requestedType === ServiceIsolationType::InvoiceOverdue && $invoice === null) {
            throw ValidationException::withMessages([
                'invoice_id' => 'Invoice overdue isolation requires an invoice reference.',
            ]);
        }

        if ($invoice === null) {
            return;
        }

        if ((int) $invoice->service_id !== (int) $service->id) {
            throw ValidationException::withMessages([
                'invoice_id' => 'The selected invoice does not belong to the selected service.',
            ]);
        }

        if ($requestedType === ServiceIsolationType::InvoiceOverdue && ! $invoice->isOverdue()) {
            throw ValidationException::withMessages([
                'invoice_id' => 'Invoice overdue isolation can only be created for overdue invoices.',
            ]);
        }
    }

    private function ensureNoOpenIsolation(Service $service): void
    {
        $hasOpenIsolation = ServiceIsolation::query()
            ->where('service_id', $service->id)
            ->open()
            ->lockForUpdate()
            ->exists();

        if (! $hasOpenIsolation) {
            return;
        }

        throw ValidationException::withMessages([
            'service_id' => 'The selected service already has an open isolation record.',
        ]);
    }

    private function resolveIsolationType(?ServiceIsolationType $requestedType, ?Invoice $invoice): ServiceIsolationType
    {
        if ($requestedType !== null) {
            return $requestedType;
        }

        return $invoice?->isOverdue() === true
            ? ServiceIsolationType::InvoiceOverdue
            : ServiceIsolationType::Manual;
    }

    private function resolveAddressListName(Service $service): string
    {
        $addressListName = trim((string) (config('mikrotik.isolation.address_list_name') ?? $service->address_list_name ?? ''));

        if ($addressListName !== '') {
            return $addressListName;
        }

        return Str::limit('ISOLIR_CUSTOMER', 100, '');
    }

    private function syncRouterOperationIsolationState(Service $service, ?ServiceIsolation $isolation, bool $applied): void
    {
        $operationStatus = $service->relationLoaded('routerOperationStatus')
            ? $service->routerOperationStatus
            : $service->routerOperationStatus()->first();

        $operationStatus ??= new ServiceRouterOperationStatus([
            'service_id' => $service->id,
        ]);

        $operationStatus->fill([
            'router_id' => $service->router_id,
            'access_mode' => $service->resolvedAccessMode()->value,
            'isolation_target_type' => $isolation?->target_type?->value ?? $service->resolvedIsolationTargetType()->value,
            'pppoe_username' => $service->operationalPppoeUsername(),
            'static_ip_address' => $service->operationalStaticIpAddress(),
            'static_mac_address' => $service->static_mac_address,
            'queue_name' => $service->static_queue_name,
            'address_list_name' => $isolation?->address_list_name ?? $service->address_list_name,
            'open_isolation_id' => $isolation?->id,
            'isolation_applied' => $applied,
            'isolation_detected_via' => $applied ? $this->resolveIsolationChannel($service) : null,
            'isolation_verified_at' => $applied ? now() : null,
        ]);

        if (! $operationStatus->exists || $operationStatus->isDirty()) {
            $operationStatus->save();
        }

        $service->setRelation('routerOperationStatus', $operationStatus);
    }

    private function resolveIsolationChannel(Service $service): ?string
    {
        return match ($service->isolation_method?->value) {
            'ppp_profile' => 'ppp_profile',
            'queue' => 'queue',
            'address_list' => 'address_list',
            default => null,
        };
    }

    private function defaultRedirectUrl(): ?string
    {
        $redirectUrl = config('billing.ftth_isolation.default_redirect_url');

        if (! is_string($redirectUrl)) {
            return null;
        }

        $redirectUrl = trim($redirectUrl);

        return $redirectUrl !== ''
            ? $redirectUrl
            : null;
    }

    private function appendNotes(?string $existingNotes, ?string $incomingNotes): ?string
    {
        $existingNotes = $this->normalizeNullableString($existingNotes);
        $incomingNotes = $this->normalizeNullableString($incomingNotes);

        if ($incomingNotes === null) {
            return $existingNotes;
        }

        if ($existingNotes === null) {
            return $incomingNotes;
        }

        return $existingNotes."\n\n".$incomingNotes;
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : null;
    }

    private function loadIsolation(ServiceIsolation $serviceIsolation): ServiceIsolation
    {
        return $serviceIsolation->refresh()->load(self::RELATIONS);
    }
}
