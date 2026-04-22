<?php

namespace App\Console\Commands;

use App\Enums\MikrotikSyncJobStatus;
use App\Jobs\SyncMikrotikVidJob;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikVidSyncService;
use App\Support\Automation\InteractsWithAutomationLogging;
use Illuminate\Console\Command;
use Throwable;

class SyncMikrotikVidCommand extends Command
{
    use InteractsWithAutomationLogging;

    protected $signature = 'mikrotik:sync-vids
        {router? : Optional router ID to synchronize}
        {--provider= : Override Mikrotik inventory provider (for example: fake or routeros-api)}
        {--queue : Dispatch queued sync job(s) instead of running inline}';

    protected $description = 'Synchronize VID inventory from the configured Mikrotik provider.';

    public function handle(MikrotikVidSyncService $syncService): int
    {
        $provider = is_string($this->option('provider')) && trim((string) $this->option('provider')) !== ''
            ? trim((string) $this->option('provider'))
            : null;
        $routers = $this->resolveRouters();

        if ($routers->isEmpty()) {
            $this->warn('No routers matched the synchronization target.');

            return self::SUCCESS;
        }

        if ((bool) $this->option('queue')) {
            foreach ($routers as $router) {
                SyncMikrotikVidJob::dispatch($router->id, $provider);
            }

            $this->info(sprintf('Queued %d Mikrotik VID sync job(s).', $routers->count()));

            return self::SUCCESS;
        }

        $failedRouters = 0;

        foreach ($routers as $router) {
            try {
                $job = $syncService->sync($router, $provider);
            } catch (Throwable $throwable) {
                report($throwable);
                $failedRouters++;
                $this->logAutomationFailed('network.sync_mikrotik_vid.command', $throwable, [
                    'router_id' => $router->id,
                    'router_code' => $router->router_code,
                ]);

                $this->error(sprintf(
                    'Router %s (%s) failed: %s',
                    $router->router_code,
                    $router->router_name,
                    $throwable->getMessage(),
                ));

                continue;
            }

            $this->info(sprintf(
                'Sync job #%d completed for router %s (%s).',
                $job->id,
                $router->router_code,
                $router->router_name,
            ));
            $this->line(sprintf(
                'Status: %s | Found: %d | New: %d | Updated: %d | Conflict: %d',
                $job->job_status?->value ?? 'unknown',
                $job->total_found,
                $job->total_new,
                $job->total_updated,
                $job->total_conflict,
            ));

            if ($job->summary !== null && $job->summary !== '') {
                $this->line($job->summary);
            }

            if ($job->job_status !== MikrotikSyncJobStatus::Completed) {
                $failedRouters++;
            }
        }

        return $failedRouters === 0
            ? self::SUCCESS
            : self::FAILURE;
    }

    private function resolveRouters()
    {
        if ($this->argument('router') !== null) {
            $router = Router::query()->find($this->argument('router'));

            if ($router === null) {
                $this->error('Router not found.');

                return collect();
            }

            return collect([$router]);
        }

        return Router::query()
            ->where('is_active', true)
            ->orderBy('router_code')
            ->get();
    }
}
