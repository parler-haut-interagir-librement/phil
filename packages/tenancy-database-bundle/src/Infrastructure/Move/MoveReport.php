<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Move;

use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * A move that happened: the rows copied to each table of the target, and whether the source was emptied of them.
 */
final readonly class MoveReport
{
    /**
     * @param array<string, int> $rows the rows copied, by table; empty when the tenant was already in the target
     */
    public function __construct(
        public TenantId $tenant,
        public Placement $from,
        public Placement $to,
        public array $rows,
        public bool $sourcePurged,
    ) {
    }

    public function moved(): bool
    {
        return !$this->from->equals($this->to);
    }
}
