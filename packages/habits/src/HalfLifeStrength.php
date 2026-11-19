<?php

declare(strict_types=1);

namespace Phil\Habits;

use Override;
use Phil\Habits\Contract\StrengthModel;
use Phil\Habits\Exception\InvalidHabit;

/**
 * The strength as an exponentially smoothed average of how much of each period was done, whose memory halves every
 * so many days — fourteen by default.
 *
 * After a period of `d` days done for a share `c`, the strength `s` moves towards `c` by a weight `w`:
 *
 *     w = 1 − 2^(−d / h)        s′ = s + w × (c − s)
 *
 * where `h` is the half-life in days. A period's weight depends on the days it stands for, not on the schedule: a week
 * done three times out of three weighs as much as seven days done one by one, so that the same habit kept daily or
 * weekly reaches the same strength in the same time. A missed period lowers the strength by a share of what it is,
 * never back to zero at once; one done in part raises or lowers it towards that part.
 */
final readonly class HalfLifeStrength implements StrengthModel
{
    public const float DEFAULT_HALF_LIFE = 14.0;

    /**
     * @throws InvalidHabit
     */
    public function __construct(
        public float $halfLife = self::DEFAULT_HALF_LIFE,
    ) {
        if (is_nan($halfLife) || $halfLife < 1.0 || $halfLife > 365.0) {
            throw InvalidHabit::because('The half-life of a strength is from 1 to 365 days.');
        }
    }

    #[Override]
    public function next(float $strength, float $completion, float $days): float
    {
        $weight = 1.0 - 2.0 ** (-$days / $this->halfLife);

        return max(0.0, min(1.0, $strength + $weight * ($completion - $strength)));
    }
}
