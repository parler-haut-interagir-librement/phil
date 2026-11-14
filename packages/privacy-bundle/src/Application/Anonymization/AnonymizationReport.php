<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Anonymization;

/**
 * How many rows each table had rewritten. A row whose anonymized columns were all null is not counted: nothing in
 * it was written.
 */
final readonly class AnonymizationReport
{
    /**
     * @param array<string, int> $rows by table
     */
    public function __construct(
        public array $rows,
    ) {
    }

    public function total(): int
    {
        return array_sum($this->rows);
    }
}
