<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Exception;

use RuntimeException;

/**
 * An enrollment from a catalogue the bundle does not make: the catalogue is not shown to the learner, the course is
 * not in it, it is not open to self-enrollment, or it has a price and is bought instead — `$purchase` then says so.
 */
final class EnrollmentRefused extends RuntimeException implements CoursesException
{
    public function __construct(
        string $reason,
        public readonly bool $purchase = false,
    ) {
        parent::__construct($reason);
    }

    public static function because(string $reason): self
    {
        return new self($reason);
    }

    /**
     * The course has a price in this catalogue: the learner buys it, and is enrolled once the order is paid.
     */
    public static function purchase(): self
    {
        return new self('This course is bought before the enrollment.', true);
    }
}
