<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Domain\Training;

use DateTimeImmutable;
use Phil\ReportingBundle\Domain\Exception\InvalidFigures;

/**
 * A training activity a learning system records — a course completed, a session attended —: the day it counts on, its
 * hours in hundredths, what it cost and the part of that cost the law counts, in minor units of the source's currency,
 * and the organisational unit of the learner when there is one.
 */
final readonly class TrainingActivity
{
    /** The most hours of one activity, in hundredths: ten thousand hours. */
    public const int MAX_HUNDREDTHS = 1_000_000;

    /** The largest cost of one activity, in minor units: ten billion dollars. */
    public const int MAX_AMOUNT = 1_000_000_000_000;

    private const string UNIT = '/^[\x21-\x7E]{1,128}$/D';

    /**
     * @param int         $hundredthsOfHours the hours in hundredths: 150 for an hour and a half
     * @param int         $cost              the cost, in minor units: cents for dollars
     * @param int         $eligibleCost      the part of the cost the law counts, at most the cost
     * @param string|null $unit              the identifier of the learner's organisational unit: 1 to 128 printable ASCII characters
     *
     * @throws InvalidFigures when an amount is negative or beyond its bound, the eligible cost above the cost, or the unit out of shape
     */
    public function __construct(
        public DateTimeImmutable $on,
        public int $hundredthsOfHours,
        public int $cost = 0,
        public int $eligibleCost = 0,
        public ?string $unit = null,
    ) {
        if ($hundredthsOfHours < 0 || $hundredthsOfHours > self::MAX_HUNDREDTHS) {
            throw InvalidFigures::because(\sprintf('the hours of a training activity are from 0 to %d hundredths', self::MAX_HUNDREDTHS));
        }

        if ($cost < 0 || $cost > self::MAX_AMOUNT) {
            throw InvalidFigures::because(\sprintf('the cost of a training activity is from 0 to %d', self::MAX_AMOUNT));
        }

        if ($eligibleCost < 0 || $eligibleCost > $cost) {
            throw InvalidFigures::because('the eligible cost of a training activity is from 0 to its cost');
        }

        if (null !== $unit && 1 !== preg_match(self::UNIT, $unit)) {
            throw InvalidFigures::because('a unit is named by 1 to 128 printable ASCII characters');
        }
    }
}
