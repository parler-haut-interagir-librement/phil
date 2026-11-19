<?php

declare(strict_types=1);

namespace Phil\Habits;

use Phil\Habits\Exception\InvalidHabit;

/**
 * The streak lengths and strength percentages an application celebrates, which `Habits` dispatches an event for when a
 * check-in reaches them.
 */
final readonly class Milestones
{
    public const int MAX_MILESTONES = 50;

    /** @var list<positive-int> */
    public array $streaks;

    /** @var list<int<1, 100>> */
    public array $strengths;

    /**
     * @param list<int> $streaks   streak lengths, in periods of the habit's schedule
     * @param list<int> $strengths strength percentages, from 1 to 100
     *
     * @throws InvalidHabit
     */
    public function __construct(
        array $streaks = [3, 7, 14, 30, 60, 100, 200, 365],
        array $strengths = [25, 50, 75, 100],
    ) {
        if (\count($streaks) > self::MAX_MILESTONES || \count($strengths) > self::MAX_MILESTONES) {
            throw InvalidHabit::because(\sprintf('An application celebrates at most %d streak lengths and %d strengths.', self::MAX_MILESTONES, self::MAX_MILESTONES));
        }

        $lengths = [];

        foreach ($streaks as $length) {
            if ($length < 1) {
                throw InvalidHabit::because('A streak length to celebrate is a whole number from 1.');
            }

            $lengths[$length] = $length;
        }

        $percents = [];

        foreach ($strengths as $percent) {
            if ($percent < 1 || $percent > 100) {
                throw InvalidHabit::because('A strength to celebrate is a percentage from 1 to 100.');
            }

            $percents[$percent] = $percent;
        }

        ksort($lengths);
        ksort($percents);
        $this->streaks = array_values($lengths);
        $this->strengths = array_values($percents);
    }

    /**
     * The streak lengths a streak passed going from one length to another.
     *
     * @return list<positive-int>
     */
    public function streaksBetween(int $before, int $after): array
    {
        return array_values(array_filter($this->streaks, static fn (int $length): bool => $length > $before && $length <= $after));
    }

    /**
     * The strength percentages a strength passed going from one percentage to another.
     *
     * @return list<int<1, 100>>
     */
    public function strengthsBetween(int $before, int $after): array
    {
        return array_values(array_filter($this->strengths, static fn (int $percent): bool => $percent > $before && $percent <= $after));
    }
}
