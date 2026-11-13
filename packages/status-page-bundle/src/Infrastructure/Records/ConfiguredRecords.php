<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Infrastructure\Records;

use Override;
use Phil\StatusPageBundle\Application\Contract\StatusRecords;
use Phil\StatusPageBundle\Application\Records;
use Phil\StatusPageBundle\Application\RecordsReader;

/**
 * Incidents and maintenances written in the configuration, under `records`, read once when the service is built.
 */
final readonly class ConfiguredRecords implements StatusRecords
{
    private Records $records;

    /**
     * @param array<mixed> $data
     */
    public function __construct(array $data, RecordsReader $reader = new RecordsReader())
    {
        $this->records = $reader->read($data);
    }

    #[Override]
    public function incidents(): array
    {
        return $this->records->incidents;
    }

    #[Override]
    public function maintenances(): array
    {
        return $this->records->maintenances;
    }
}
