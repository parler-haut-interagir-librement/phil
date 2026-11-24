<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A line, a stock figure or a formula's input out of its bounds: an empty SKU, a quantity below one, a negative
 * amount, amounts in two currencies on one line.
 */
final class InvalidFigure extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
