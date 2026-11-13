<?php

declare(strict_types=1);

namespace Phil\Stockroom\Exception;

use DomainException;

/**
 * A movement that takes more than a place holds, or an item that cannot move as it is: retired, under repair, or a
 * tracked unit already in stock.
 */
final class InsufficientStock extends DomainException implements StockroomException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
