<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;

/**
 * A period of time, its start included and its end excluded, over which credits of continuing education are counted.
 */
final readonly class Period
{
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $until,
    ) {
        if ($from >= $until) {
            throw InvalidValue::because('A period ends after it starts.');
        }
    }

    public function contains(DateTimeImmutable $moment): bool
    {
        return $moment >= $this->from && $moment < $this->until;
    }
}
