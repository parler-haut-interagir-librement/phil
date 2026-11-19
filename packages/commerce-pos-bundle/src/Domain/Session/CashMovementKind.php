<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Session;

/**
 * Why cash went into the drawer or out of it.
 */
enum CashMovementKind: string
{
    /** The float the session opens with. */
    case Float = 'float';

    /** Cash taken for a sale, rounded to the register's increment. */
    case Sale = 'sale';

    /** Cash given back for a refund. */
    case Refund = 'refund';

    /** Cash put in for another reason: change brought from the safe. */
    case PaidIn = 'paid_in';

    /** Cash taken out to pay for something: a delivery, supplies. */
    case PaidOut = 'paid_out';

    /** Cash taken to the safe or the bank during the session. */
    case Drop = 'drop';

    /**
     * Whether the movement puts cash into the drawer.
     */
    public function addsCash(): bool
    {
        return match ($this) {
            self::Float, self::Sale, self::PaidIn => true,
            self::Refund, self::PaidOut, self::Drop => false,
        };
    }
}
