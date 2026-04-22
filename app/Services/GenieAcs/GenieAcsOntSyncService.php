<?php

namespace App\Services\GenieAcs;

use App\Contracts\GenieAcs\GenieAcsOntDataProvider;
use App\Models\Ont;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenieAcsOntSyncService
{
    public function __construct(
        private readonly GenieAcsOntDataProviderResolver $providerResolver,
        private readonly LogManager $logManager,
    ) {}

    /**
     * @return array{
     *     result:string,
     *     provider:string,
     *     ont_id:int,
     *     ont_sn:string,
     *     updated_fields:list<string>
     * }
     */
    public function syncOnt(Ont $ont, ?string $providerName = null): array
    {
        $provider = $this->providerResolver->resolve($providerName);

        return $this->syncSingleOnt($ont, $provider);
    }

    /**
     * @return array{provider:string,checked:int,updated:int,unchanged:int,not_found:int,failed:int}
     */
    public function syncQuery(Builder $query, ?string $providerName = null, int $chunkSize = 100): array
    {
        $provider = $this->providerResolver->resolve($providerName);
        $summary = [
            'provider' => $provider->name(),
            'checked' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'not_found' => 0,
            'failed' => 0,
        ];

        $workingQuery = clone $query;

        $workingQuery
            ->select('id')
            ->reorder('id')
            ->chunkById($chunkSize, function ($onts) use (&$summary, $provider): void {
                foreach ($onts as $ontReference) {
                    $summary['checked']++;

                    try {
                        $result = $this->syncSingleOnt($ontReference, $provider);
                        $summary[$result['result']]++;
                    } catch (Throwable $throwable) {
                        report($throwable);
                        $summary['failed']++;

                        $this->logger()->error('GenieACS ONT sync failed.', [
                            'provider' => $provider->name(),
                            'ont_id' => $ontReference->id,
                            'message' => $throwable->getMessage(),
                        ]);
                    }
                }
            });

        return $summary;
    }

    /**
     * @return array{
     *     result:string,
     *     provider:string,
     *     ont_id:int,
     *     ont_sn:string,
     *     updated_fields:list<string>
     * }
     */
    private function syncSingleOnt(Ont $ont, GenieAcsOntDataProvider $provider): array
    {
        $ont = Ont::query()->findOrFail($ont->getKey());
        $snapshot = $provider->fetchOntSnapshot($ont);

        if ($snapshot === null) {
            $this->logger()->warning('GenieACS snapshot not found for ONT.', [
                'provider' => $provider->name(),
                'ont_id' => $ont->id,
                'ont_sn' => $ont->ont_sn,
                'genieacs_device_id' => $ont->genieacs_device_id,
            ]);

            return [
                'result' => 'not_found',
                'provider' => $provider->name(),
                'ont_id' => $ont->id,
                'ont_sn' => $ont->ont_sn,
                'updated_fields' => [],
            ];
        }

        $syncedAt = now();

        return DB::transaction(function () use ($ont, $snapshot, $syncedAt, $provider): array {
            $lockedOnt = Ont::query()
                ->lockForUpdate()
                ->findOrFail($ont->getKey());

            $attributes = $snapshot->toOntAttributes($syncedAt);
            $updatedFields = $this->extractRelevantChangedAttributes($lockedOnt, $attributes);

            $lockedOnt->fill($attributes);

            if (! $lockedOnt->exists || $lockedOnt->isDirty()) {
                $lockedOnt->save();
            }

            $result = $updatedFields === []
                ? 'unchanged'
                : 'updated';

            $this->logger()->info('GenieACS ONT synchronized.', [
                'provider' => $provider->name(),
                'ont_id' => $lockedOnt->id,
                'ont_sn' => $lockedOnt->ont_sn,
                'result' => $result,
                'updated_fields' => $updatedFields,
            ]);

            return [
                'result' => $result,
                'provider' => $provider->name(),
                'ont_id' => $lockedOnt->id,
                'ont_sn' => $lockedOnt->ont_sn,
                'updated_fields' => $updatedFields,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<string>
     */
    private function extractRelevantChangedAttributes(Ont $ont, array $attributes): array
    {
        $ignore = ['genieacs_last_synced_at'];
        $changed = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $ignore, true)) {
                continue;
            }

            if ($ont->getAttribute($key) != $value) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    private function logger()
    {
        $channel = (string) (config('genieacs.logging.channel') ?: config('logging.default', 'stack'));

        return $this->logManager->channel($channel);
    }
}
