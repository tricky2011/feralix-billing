<?php

namespace App\Console\Commands;

use App\Jobs\SyncGenieAcsOntJob;
use App\Models\Ont;
use App\Services\GenieAcs\GenieAcsOntSyncService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Console\Command;
use Throwable;

class SyncGenieAcsOntCommand extends Command
{
    use InteractsWithAutomationLogging;

    protected $signature = 'genieacs:sync-onts
        {ont? : Optional ONT ID to synchronize}
        {--provider= : Override GenieACS provider (for example: fake or nbi)}
        {--chunk= : Number of ONTs to process per database chunk}
        {--queue : Dispatch queued sync job(s) instead of running inline}';

    protected $description = 'Synchronize ONT status and telemetry from GenieACS.';

    public function handle(GenieAcsOntSyncService $syncService): int
    {
        $provider = is_string($this->option('provider')) && trim((string) $this->option('provider')) !== ''
            ? trim((string) $this->option('provider'))
            : null;
        $chunkSize = max(1, (int) ($this->option('chunk') ?: config('automation.monitoring.genieacs.chunk', 100)));

        if ($this->argument('ont') !== null) {
            $ont = Ont::query()->find($this->argument('ont'));

            if ($ont === null) {
                $this->error('ONT not found.');

                return self::FAILURE;
            }

            if ((bool) $this->option('queue')) {
                SyncGenieAcsOntJob::dispatch($ont->id, $provider, $chunkSize);
                $this->info(sprintf('Queued GenieACS sync job for ONT %s.', $ont->ont_sn));

                return self::SUCCESS;
            }

            try {
                $result = $syncService->syncOnt($ont, $provider);
            } catch (Throwable $throwable) {
                report($throwable);
                $this->logAutomationFailed('monitoring.sync_genieacs_ont.command', $throwable, [
                    'ont_id' => $ont->id,
                    'provider' => $provider,
                ]);
                $this->error($throwable->getMessage());

                return self::FAILURE;
            }

            $this->info(sprintf(
                'ONT %s synchronized via %s provider.',
                $result['ont_sn'],
                $result['provider'],
            ));
            $this->line(sprintf(
                'Result: %s | Updated fields: %s',
                $result['result'],
                $result['updated_fields'] === [] ? '-' : implode(', ', $result['updated_fields']),
            ));

            return self::SUCCESS;
        }

        if ((bool) $this->option('queue')) {
            SyncGenieAcsOntJob::dispatch(null, $provider, $chunkSize);
            $this->info('Queued GenieACS ONT sync job.');

            return self::SUCCESS;
        }

        try {
            $summary = $syncService->syncQuery(
                query: Ont::query(),
                providerName: $provider,
                chunkSize: $chunkSize,
            );
        } catch (Throwable $throwable) {
            report($throwable);
            $this->logAutomationFailed('monitoring.sync_genieacs_ont.command', $throwable, [
                'provider' => $provider,
                'chunk_size' => $chunkSize,
            ]);
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Provider %s: checked %d ONT(s), updated %d, unchanged %d, not found %d, failed %d.',
            $summary['provider'],
            $summary['checked'],
            $summary['updated'],
            $summary['unchanged'],
            $summary['not_found'],
            $summary['failed'],
        ));

        return $summary['failed'] === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}
