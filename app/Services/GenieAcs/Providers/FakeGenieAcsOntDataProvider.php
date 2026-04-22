<?php

namespace App\Services\GenieAcs\Providers;

use App\Contracts\GenieAcs\GenieAcsOntDataProvider;
use App\Data\GenieAcs\GenieAcsOntSnapshot;
use App\Models\Ont;

class FakeGenieAcsOntDataProvider implements GenieAcsOntDataProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function fetchOntSnapshot(Ont $ont): ?GenieAcsOntSnapshot
    {
        $datasets = config('genieacs.providers.fake.onts', config('genieacs.fake.onts', []));
        $dataset = $this->resolveDataset($datasets, $ont);

        if (! is_array($dataset)) {
            return null;
        }

        return GenieAcsOntSnapshot::fromArray([
            'device_id' => $dataset['device_id'] ?? $ont->genieacs_device_id ?? 'fake-'.$ont->ont_sn,
            'serial_number' => $dataset['serial_number'] ?? $ont->ont_sn,
            ...$dataset,
        ]);
    }

    private function resolveDataset(mixed $datasets, Ont $ont): ?array
    {
        if (! is_array($datasets)) {
            return null;
        }

        $deviceId = is_string($ont->genieacs_device_id) ? trim($ont->genieacs_device_id) : null;
        $ontSn = trim((string) $ont->ont_sn);

        return $datasets[$deviceId]
            ?? ($deviceId !== null && $deviceId !== '' ? ($datasets['device:'.$deviceId] ?? null) : null)
            ?? $datasets[$ontSn]
            ?? $datasets['ont:'.$ont->id]
            ?? $datasets['default']
            ?? null;
    }
}
