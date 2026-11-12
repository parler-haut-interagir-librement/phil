<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\BrowserSecurityBundle\Domain\Contract\ReportRepository;
use Phil\BrowserSecurityBundle\Domain\ValueObject\ViolationReport;

/**
 * The reports recorded, in memory.
 */
final class MemoryReports implements ReportRepository
{
    /** @var list<array{ViolationReport, ?string, DateTimeImmutable}> */
    public array $recorded = [];

    public ?DateTimeImmutable $purgedBefore = null;

    #[Override]
    public function record(ViolationReport $report, ?string $client, DateTimeImmutable $at): void
    {
        $this->recorded[] = [$report, $client, $at];
    }

    #[Override]
    public function latest(int $limit): array
    {
        return [];
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        $this->purgedBefore = $before;

        return 4;
    }
}
