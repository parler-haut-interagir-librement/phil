<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Contract;

use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Exception\ChainConflict;

/**
 * Where the rows of the trail are kept, append-only.
 */
interface AuditRowRepository
{
    /**
     * The row with the lowest sequence of the segment, `null` when it has none.
     */
    public function first(string $segment): ?AuditRow;

    /**
     * The row with the highest sequence of the segment, `null` when it has none.
     */
    public function head(string $segment): ?AuditRow;

    /**
     * Appends a row; one that takes a place another row already took — another process appended first — is refused.
     *
     * @throws ChainConflict
     */
    public function append(AuditRow $row): void;

    /**
     * @return iterable<AuditRow> the rows of the segment, by sequence
     */
    public function rows(string $segment): iterable;

    /**
     * At most `$limit` rows of the segment whose sequence is above `$sequence`, by sequence, read on the
     * `(segment, sequence)` key: what a feed reads from where it stopped, whatever instants the rows carry.
     *
     * @param int $sequence 0 for the segment's first rows
     * @param int $limit    1 or more
     *
     * @return list<AuditRow>
     */
    public function rowsAfter(string $segment, int $sequence, int $limit): array;

    /**
     * @return list<string> every segment with a row
     */
    public function segments(): array;
}
