<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\BrowserSecurityBundle\Domain\ValueObject\StoredReport;
use Phil\BrowserSecurityBundle\Domain\ValueObject\ViolationReport;

/**
 * Where the violations are kept, one entry per fingerprint ({@see ViolationReport::fingerprint()}).
 */
interface ReportRepository
{
    /**
     * Adds one to the entry of the report's fingerprint, or creates it.
     *
     * @param string|null $client the anonymized address of the client, never a raw one
     */
    public function record(ViolationReport $report, ?string $client, DateTimeImmutable $at): void;

    /**
     * The entries last seen most recently first.
     *
     * @return list<StoredReport>
     */
    public function latest(int $limit): array;

    /**
     * Removes the entries last seen before a moment, and gives how many it removed.
     */
    public function purge(DateTimeImmutable $before): int;
}
