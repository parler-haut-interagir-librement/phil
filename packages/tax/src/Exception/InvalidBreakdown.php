<?php

declare(strict_types=1);

namespace Phil\Tax\Exception;

use DomainException;

/**
 * Lines that do not make one breakdown: a line on another base, or a rate that appears twice.
 */
final class InvalidBreakdown extends DomainException implements TaxException
{
    public static function anotherBase(string $code): self
    {
        return new self(\sprintf('The %s line is on another base: every rate of a breakdown applies to the same base.', $code));
    }

    public static function duplicateRate(string $code): self
    {
        return new self(\sprintf('The %s rate appears twice in one breakdown.', $code));
    }
}
