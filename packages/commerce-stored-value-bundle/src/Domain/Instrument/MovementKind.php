<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Domain\Instrument;

/**
 * Why a balance moved.
 */
enum MovementKind: string
{
    /** A gift card's first value. */
    case Issued = 'issued';

    /** Value added to a gift card. */
    case Loaded = 'loaded';

    /** Credit given to a customer: a return, a gesture. */
    case Granted = 'granted';

    /** Value spent on an order. */
    case Redeemed = 'redeemed';

    /** Value given back when a payment is refunded. */
    case Restored = 'restored';

    /** What was left, taken away when the instrument is voided. */
    case Voided = 'voided';

    /**
     * Whether the movement adds to the balance.
     */
    public function credits(): bool
    {
        return match ($this) {
            self::Issued, self::Loaded, self::Granted, self::Restored => true,
            self::Redeemed, self::Voided => false,
        };
    }
}
