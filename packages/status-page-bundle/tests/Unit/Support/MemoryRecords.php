<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Tests\Unit\Support;

use Override;
use Phil\StatusPageBundle\Application\Contract\StatusRecords;
use Phil\StatusPageBundle\Domain\ValueObject\Incident;
use Phil\StatusPageBundle\Domain\ValueObject\ScheduledMaintenance;

final readonly class MemoryRecords implements StatusRecords
{
    /**
     * @param list<Incident>             $incidents
     * @param list<ScheduledMaintenance> $maintenances
     */
    public function __construct(
        private array $incidents = [],
        private array $maintenances = [],
    ) {
    }

    #[Override]
    public function incidents(): array
    {
        return $this->incidents;
    }

    #[Override]
    public function maintenances(): array
    {
        return $this->maintenances;
    }
}
