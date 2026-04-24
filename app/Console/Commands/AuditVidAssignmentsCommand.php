<?php

namespace App\Console\Commands;

use App\Services\Inventory\VidAssignmentService;
use Illuminate\Console\Command;

class AuditVidAssignmentsCommand extends Command
{
    protected $signature = 'services:audit-vid-assignments
        {--repair : Apply safe repairs for stale VID ownership records}';

    protected $description = 'Audit Service <-> VID assignment invariants.';

    public function handle(VidAssignmentService $vidAssignmentService): int
    {
        if ($this->option('repair')) {
            $result = $vidAssignmentService->repair();

            $this->info('VID assignment repair summary:');
            $this->line($this->formatKeyValues($result['repair_summary']));

            $report = $result['after'];
        } else {
            $report = $vidAssignmentService->audit();
        }

        $this->renderReport($report);

        return ($report['summary']['total_issues'] ?? 0) > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function renderReport(array $report): void
    {
        $this->info('VID assignment audit summary:');

        foreach ($report['summary'] as $key => $count) {
            $this->line(sprintf('%s: %d', $key, $count));
        }

        foreach ($report['issues'] as $issueType => $issues) {
            if ($issues === []) {
                continue;
            }

            $this->newLine();
            $this->warn($issueType);

            foreach (array_slice($issues, 0, 10) as $issue) {
                $this->line('- '.$this->formatKeyValues($issue));
            }

            if (count($issues) > 10) {
                $this->line(sprintf('- ... %d more issue(s)', count($issues) - 10));
            }
        }
    }

    private function formatKeyValues(array $values): string
    {
        return collect($values)
            ->map(fn (mixed $value, string $key): string => $key.'='.$this->stringify($value))
            ->implode(' ');
    }

    private function stringify(mixed $value): string
    {
        if (is_array($value)) {
            return '['.collect($value)->map(fn (mixed $item): string => $this->stringify($item))->implode(',').']';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        return (string) $value;
    }
}
