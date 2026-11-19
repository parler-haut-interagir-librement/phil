<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Domain\Status;

/**
 * Where a loan stands: reserved for a span to come, active while the member has the copy, then returned, cancelled
 * before it began, or lost.
 */
enum LoanStatus: string
{
    case Reserved = 'reserved';
    case Active = 'active';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
    case Lost = 'lost';

    /**
     * Whether a loan in this state keeps the copy for its span.
     */
    public function holdsCopy(): bool
    {
        return self::Reserved === $this || self::Active === $this;
    }

    /**
     * @return list<self>
     */
    public static function holding(): array
    {
        return [self::Reserved, self::Active];
    }

    /**
     * @return list<self>
     */
    public static function closed(): array
    {
        return [self::Returned, self::Cancelled, self::Lost];
    }
}
