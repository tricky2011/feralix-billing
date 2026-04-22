<?php

namespace App\Actions\Mikrotik;

use App\Data\Mikrotik\MikrotikVidInventoryRecord;
use App\Models\Vid;

class DetectMikrotikVidConflictAction
{
    public function execute(Vid $localVid, MikrotikVidInventoryRecord $remoteRecord): array
    {
        $localCriticalFields = [
            'vid' => $localVid->vid,
            'subnet_cidr' => $localVid->subnet_cidr,
            'gateway_ip' => $localVid->gateway_ip,
            'pool_start_ip' => $localVid->pool_start_ip,
            'pool_end_ip' => $localVid->pool_end_ip,
        ];

        $mismatches = [];

        foreach ($remoteRecord->criticalFields() as $field => $remoteValue) {
            $localValue = $localCriticalFields[$field] ?? null;

            if ($localValue === $remoteValue) {
                continue;
            }

            $mismatches[$field] = [
                'local' => $localValue,
                'remote' => $remoteValue,
            ];
        }

        return $mismatches;
    }
}
