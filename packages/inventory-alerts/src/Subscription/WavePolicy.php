<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Subscription;

use Phil\InventoryAlerts\Exception\InvalidInput;

/**
 * How many subscribers one wave tells: a few per unit back in stock, so a small restock does not reach a thousand
 * people for three units, never fewer than the minimum, never more than the maximum.
 */
final readonly class WavePolicy
{
    public const int MAX_WAVE = 10_000;

    public function __construct(
        public int $subscribersPerUnit = 2,
        public int $minimum = 10,
        public int $maximum = 500,
    ) {
        if ($subscribersPerUnit < 1 || $subscribersPerUnit > self::MAX_WAVE) {
            throw InvalidInput::because('number of subscribers per unit', 'must be from 1 to 10,000');
        }

        if ($minimum < 1 || $minimum > $maximum) {
            throw InvalidInput::because('minimum of a wave', 'must be from 1 to the maximum');
        }

        if ($maximum > self::MAX_WAVE) {
            throw InvalidInput::because('maximum of a wave', 'must be at most 10,000');
        }
    }

    /**
     * The size of the wave for a number of units back in stock; 0 when none is.
     */
    public function sizeFor(int $available): int
    {
        if ($available <= 0) {
            return 0;
        }

        return min($this->maximum, max($this->minimum, $available * $this->subscribersPerUnit));
    }
}
