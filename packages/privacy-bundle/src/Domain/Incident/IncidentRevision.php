<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Incident;

use DateTimeImmutable;

/**
 * A value a line of the register held before a correction: which field, what it was, and when it was replaced.
 *
 * Corrections are numbered per incident; the fields changed by one correction share its number.
 */
final readonly class IncidentRevision
{
    public function __construct(
        public string $incidentId,
        public int $sequence,
        public string $field,
        public ?string $previous,
        public DateTimeImmutable $revisedAt,
    ) {
    }
}
