<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Contract;

use DateTimeImmutable;

/**
 * Writes the anchor of a period outside the database.
 *
 * Chaining alone is undone by anyone who can rewrite the whole chain. The external anchor is what makes a
 * rewrite detectable, **and only if its destination is append-only**: an anchor written with a credential
 * that can also delete it is rewritten by the same administrator who rewrites the chain.
 *
 * The adapter belongs to the application: object storage under object lock in compliance mode, WORM, or at
 * the very least a credential that holds write access without delete or overwrite.
 */
interface AnchorWriter
{
    /**
     * @param string            $segment   the identifier of the anchored segment (partition, or segment between two discontinuities)
     * @param DateTimeImmutable $periodEnd the end of the anchored period
     * @param string            $firstHash the hash of the first row of the period, to verify the boundaries
     * @param string            $headHash  the hash of the last row of the period
     */
    public function write(string $segment, DateTimeImmutable $periodEnd, string $firstHash, string $headHash): void;
}
