<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Training;

use Phil\CoursesBundle\Domain\Exception\InvalidCourse;

/**
 * What a completion cost, in minor units of a currency, and the part of that cost the law counts.
 */
final readonly class TrainingCost
{
    /** The largest cost of one completion, in minor units: ten billion dollars. */
    public const int MAX_AMOUNT = 1_000_000_000_000;

    /**
     * @throws InvalidCourse when the cost is negative or beyond its bound, or the eligible cost negative or above the cost
     */
    public function __construct(
        public int $cost,
        public int $eligibleCost = 0,
    ) {
        if ($cost < 0 || $cost > self::MAX_AMOUNT) {
            throw InvalidCourse::because(\sprintf('The cost of a completion is from 0 to %d.', self::MAX_AMOUNT));
        }

        if ($eligibleCost < 0 || $eligibleCost > $cost) {
            throw InvalidCourse::because('The eligible cost of a completion is from 0 to its cost.');
        }
    }

    public static function none(): self
    {
        return new self(0);
    }
}
