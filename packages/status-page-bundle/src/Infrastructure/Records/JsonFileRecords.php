<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Infrastructure\Records;

use JsonException;
use Override;
use Phil\StatusPageBundle\Application\Contract\StatusRecords;
use Phil\StatusPageBundle\Application\Records;
use Phil\StatusPageBundle\Application\RecordsReader;
use Phil\StatusPageBundle\Domain\Exception\InvalidStatusRecord;

/**
 * Incidents and maintenances in a JSON file the operators edit — on the server, by a deployment, or from another
 * machine —, read on each page so a new incident shows at once. A file that does not exist holds nothing; a file
 * larger than `MAX_BYTES`, or that is not the JSON of `RecordsReader`, is refused.
 */
final readonly class JsonFileRecords implements StatusRecords
{
    public const int MAX_BYTES = 1_048_576;

    public function __construct(
        private string $path,
        private RecordsReader $reader = new RecordsReader(),
    ) {
    }

    #[Override]
    public function incidents(): array
    {
        return $this->records()->incidents;
    }

    #[Override]
    public function maintenances(): array
    {
        return $this->records()->maintenances;
    }

    private function records(): Records
    {
        if (!is_file($this->path)) {
            return new Records();
        }

        $json = file_get_contents($this->path, length: self::MAX_BYTES + 1);

        if (false === $json || \strlen($json) > self::MAX_BYTES) {
            throw InvalidStatusRecord::because(\sprintf('The records file is readable and %d bytes at most.', self::MAX_BYTES));
        }

        try {
            $data = json_decode($json, true, 16, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidStatusRecord::because('The records file is JSON.');
        }

        if (!\is_array($data)) {
            throw InvalidStatusRecord::because('The records file holds a JSON object.');
        }

        return $this->reader->read($data);
    }
}
