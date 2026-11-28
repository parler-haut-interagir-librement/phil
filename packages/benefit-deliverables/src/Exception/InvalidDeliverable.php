<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Exception;

use InvalidArgumentException;

/**
 * A value of the package refused its own construction: a period, a benefit, a member, a page or a list out of its
 * bounds.
 */
final class InvalidDeliverable extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
