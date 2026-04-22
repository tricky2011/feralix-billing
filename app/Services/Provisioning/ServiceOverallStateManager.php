<?php

namespace App\Services\Provisioning;

use App\Enums\MonitorPppoeStatus;
use App\Enums\ServiceOverallStatus;
use App\Models\Service;
use App\Models\ServiceMonitorPppoeStatus;

class ServiceOverallStateManager
{
    public function desiredStatus(Service $service, ?ServiceMonitorPppoeStatus $monitorSnapshot = null): ServiceOverallStatus
    {
        $currentOverallStatus = $this->currentOverallStatus($service);

        if ($currentOverallStatus !== ServiceOverallStatus::Down) {
            return $currentOverallStatus;
        }

        return $monitorSnapshot?->base_overall_status instanceof ServiceOverallStatus
            ? $monitorSnapshot->base_overall_status
            : $currentOverallStatus;
    }

    public function effectiveStatus(
        ServiceOverallStatus $desiredStatus,
        ?MonitorPppoeStatus $monitorStatus,
    ): ServiceOverallStatus {
        if ($monitorStatus === MonitorPppoeStatus::Offline && $desiredStatus->isMonitorSensitive()) {
            return ServiceOverallStatus::Down;
        }

        return $desiredStatus;
    }

    public function syncDesiredStatus(
        Service $service,
        ServiceOverallStatus $desiredStatus,
        ?ServiceMonitorPppoeStatus $monitorSnapshot = null,
    ): ServiceOverallStatus {
        $monitorSnapshot ??= $this->resolveMonitorSnapshot($service);

        if ($monitorSnapshot !== null) {
            $monitorSnapshot->fill([
                'base_overall_status' => $desiredStatus->value,
            ]);

            if ($monitorSnapshot->isDirty()) {
                $monitorSnapshot->save();
            }
        }

        return $this->effectiveStatus($desiredStatus, $monitorSnapshot?->status);
    }

    private function resolveMonitorSnapshot(Service $service): ?ServiceMonitorPppoeStatus
    {
        if ($service->relationLoaded('pppoeMonitorStatus')) {
            return $service->getRelation('pppoeMonitorStatus');
        }

        return $service->pppoeMonitorStatus()->first();
    }

    private function currentOverallStatus(Service $service): ServiceOverallStatus
    {
        return $service->overall_status instanceof ServiceOverallStatus
            ? $service->overall_status
            : ServiceOverallStatus::from((string) $service->overall_status);
    }
}
