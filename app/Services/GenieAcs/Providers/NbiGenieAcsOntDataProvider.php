<?php

namespace App\Services\GenieAcs\Providers;

use App\Contracts\GenieAcs\GenieAcsClient;
use App\Contracts\GenieAcs\GenieAcsOntDataProvider;
use App\Data\GenieAcs\GenieAcsOntSnapshot;
use App\Models\Ont;
use App\Services\GenieAcs\GenieAcsOntSnapshotMapper;
use Illuminate\Log\LogManager;

class NbiGenieAcsOntDataProvider implements GenieAcsOntDataProvider
{
    public function __construct(
        private readonly GenieAcsClient $client,
        private readonly GenieAcsOntSnapshotMapper $mapper,
        private readonly LogManager $logManager,
    ) {}

    public function name(): string
    {
        return 'nbi';
    }

    public function fetchOntSnapshot(Ont $ont): ?GenieAcsOntSnapshot
    {
        $device = $this->findByDeviceId($ont) ?? $this->findBySerialNumber($ont);

        if ($device === null) {
            return null;
        }

        return $this->mapper->map($device);
    }

    private function findByDeviceId(Ont $ont): ?array
    {
        if (! is_string($ont->genieacs_device_id) || trim($ont->genieacs_device_id) === '') {
            return null;
        }

        $devices = $this->client->findDevices(
            query: ['_id' => trim($ont->genieacs_device_id)],
            projection: $this->mapper->projection(),
            limit: 1,
        );

        return $devices[0] ?? null;
    }

    private function findBySerialNumber(Ont $ont): ?array
    {
        $serialNumber = trim((string) $ont->ont_sn);

        if ($serialNumber === '') {
            return null;
        }

        $queryPaths = array_values(array_filter(
            (array) config('genieacs.identifiers.serial_query_paths', []),
            static fn (mixed $path): bool => is_string($path) && trim($path) !== '',
        ));

        if ($queryPaths === []) {
            return null;
        }

        $queries = array_map(
            static fn (string $path): array => [$path => $serialNumber],
            $queryPaths,
        );

        $query = count($queries) === 1
            ? $queries[0]
            : ['$or' => $queries];

        $devices = $this->client->findDevices(
            query: $query,
            projection: $this->mapper->projection(),
            limit: 1,
        );

        if ($devices === []) {
            $this->logger()->warning('GenieACS device not found for ONT serial number lookup.', [
                'ont_id' => $ont->id,
                'ont_sn' => $ont->ont_sn,
                'provider' => $this->name(),
            ]);
        }

        return $devices[0] ?? null;
    }

    private function logger()
    {
        $channel = (string) (config('genieacs.logging.channel') ?: config('logging.default', 'stack'));

        return $this->logManager->channel($channel);
    }
}
