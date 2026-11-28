<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Memory;

use Override;
use Phil\CodeOfConduct\Contract\ReportStore;
use Phil\CodeOfConduct\Report;
use Phil\CodeOfConduct\ReportNote;
use Phil\CodeOfConduct\ReportStatus;

/**
 * Reports in memory, for tests and demonstrations, in the order they were first saved.
 */
final class InMemoryReportStore implements ReportStore
{
    /** @var array<string, Report> */
    private array $reports = [];

    /** @var array<string, list<ReportNote>> */
    private array $notes = [];

    #[Override]
    public function get(string $id): ?Report
    {
        return $this->reports[$id] ?? null;
    }

    #[Override]
    public function save(Report $report): void
    {
        $this->reports[$report->id] = $report;
    }

    #[Override]
    public function inScope(string $scope, ?ReportStatus $status, ?string $after, int $limit): array
    {
        $found = [];
        $started = null === $after;

        foreach ($this->reports as $report) {
            if (!$started) {
                $started = $report->id === $after;

                continue;
            }

            if ($report->scope === $scope && (!$status instanceof ReportStatus || $status === $report->status)) {
                $found[] = $report;
            }
        }

        return \array_slice($found, 0, $limit);
    }

    #[Override]
    public function addNote(ReportNote $note): void
    {
        $this->notes[$note->report][] = $note;
    }

    #[Override]
    public function notes(string $report, int $limit): array
    {
        return \array_slice($this->notes[$report] ?? [], 0, $limit);
    }
}
