<?php

declare(strict_types=1);

namespace Phil\Habits\Tests\Benchmark;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Habits\CheckIn;
use Phil\Habits\CheckInStatus;
use Phil\Habits\Day;
use Phil\Habits\Habit;
use Phil\Habits\ProgressCalculator;
use Phil\Habits\Schedule;

/**
 * The progress of a habit kept for ten years — 3,653 days, three in four checked in, one in twenty skipped —, worked
 * out daily, on some days of the week and three times a week: the read behind every check-in and every page that
 * shows the streaks.
 */
final readonly class ProgressBench
{
    /** @var list<CheckIn> */
    private array $checkIns;

    private Day $today;

    public function __construct()
    {
        $first = Day::fromString('2016-11-13');
        $this->today = $first->plus(3652);
        $at = new DateTimeImmutable('2026-11-13 10:00:00 UTC');
        $checkIns = [];

        for ($day = 0; $day <= 3652; ++$day) {
            $status = match (true) {
                0 === $day % 20 => CheckInStatus::Skipped,
                0 === $day % 4 => null,
                default => CheckInStatus::Done,
            };

            if (null !== $status) {
                $checkIns[] = new CheckIn('habit', $first->plus($day), $status, null, $at);
            }
        }

        $this->checkIns = $checkIns;
    }

    public function benchDaily(): void
    {
        new ProgressCalculator()->calculate($this->habit(Schedule::daily()), $this->checkIns, $this->today);
    }

    public function benchWeekdays(): void
    {
        new ProgressCalculator()->calculate($this->habit(Schedule::onWeekdays([1, 3, 5])), $this->checkIns, $this->today);
    }

    public function benchThreeTimesAWeek(): void
    {
        new ProgressCalculator()->calculate($this->habit(Schedule::perWeek(3)), $this->checkIns, $this->today);
    }

    private function habit(Schedule $schedule): Habit
    {
        return new Habit('habit', 'member', 'Read', $schedule, new DateTimeZone('UTC'), Day::fromString('2016-11-13'));
    }
}
