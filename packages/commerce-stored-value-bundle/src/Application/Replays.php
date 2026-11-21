<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Application;

use Phil\CommerceStoredValueBundle\Domain\Model\Movement;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * An operation asked again under the idempotency key of a movement already recorded.
 *
 * @internal
 */
final class Replays
{
    /**
     * The movement recorded under the key, when there is one; a key used for another amount is a mistake, refused
     * rather than taken for the operation asked.
     *
     * @throws InvalidValue
     */
    public static function of(?Movement $recorded, Money $amount): ?Movement
    {
        if ($recorded instanceof Movement && !$recorded->amount->equals($amount)) {
            throw InvalidValue::because('That idempotency key was used for another amount.');
        }

        return $recorded;
    }
}
