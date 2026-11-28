<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Contract;

use Phil\CodeOfConduct\Report;
use Phil\CodeOfConduct\ReportNote;
use Phil\CodeOfConduct\ReportStatus;

/**
 * The reports and their notes, their texts sealed. The store never sees a plaintext.
 */
interface ReportStore
{
    public function get(string $id): ?Report;

    /**
     * Saves the report, replacing the one of the same identifier.
     */
    public function save(Report $report): void;

    /**
     * The reports of a scope, oldest first, after the report `$after`; only those of `$status` when given.
     *
     * @return list<Report> at most `$limit`
     */
    public function inScope(string $scope, ?ReportStatus $status, ?string $after, int $limit): array;

    public function addNote(ReportNote $note): void;

    /**
     * The notes of a report, oldest first.
     *
     * @return list<ReportNote> at most `$limit`
     */
    public function notes(string $report, int $limit): array;
}
