<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Contract;

use DateTimeImmutable;
use Phil\BenefitDeliverables\Export\ExportRecord;

/**
 * The journal of the shipping exports: who asked for each, when, how many rows, until when its file is kept.
 *
 * The records outlive their files: the file is erased when it expires, the record stays as the trace of the export.
 */
interface ExportJournal
{
    public function record(ExportRecord $record): void;

    public function find(string $id): ?ExportRecord;

    /**
     * @return list<ExportRecord> at most `$limit` records whose file expired before `$at` and is not erased yet,
     *                            the oldest first
     */
    public function expired(DateTimeImmutable $at, int $limit): array;

    /**
     * Notes that the file of the export was erased at `$at`; noted again, the first moment stays.
     */
    public function fileErased(string $id, DateTimeImmutable $at): void;
}
