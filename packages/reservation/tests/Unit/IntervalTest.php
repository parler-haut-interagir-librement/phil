<?php

declare(strict_types=1);

namespace Phil\Reservation\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Reservation\Exception\InvalidInterval;
use Phil\Reservation\Interval;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Interval::class)]
#[CoversClass(InvalidInterval::class)]
final class IntervalTest extends TestCase
{
    private function span(string $start, string $end): Interval
    {
        $zone = new DateTimeZone('America/Toronto');

        return new Interval(new DateTimeImmutable($start, $zone), new DateTimeImmutable($end, $zone));
    }

    public function testItKeepsItsMomentsAndTheirTimestamps(): void
    {
        $span = $this->span('2026-11-10 09:00', '2026-11-10 10:30');

        self::assertSame('2026-11-10T09:00:00-05:00', $span->start->format(\DATE_ATOM));
        self::assertSame('2026-11-10T10:30:00-05:00', $span->end->format(\DATE_ATOM));
        self::assertSame($span->start->getTimestamp(), $span->from);
        self::assertSame($span->end->getTimestamp(), $span->until);
        self::assertSame(5400, $span->seconds());
    }

    public function testItRefusesAnEndThatDoesNotComeAfterItsStart(): void
    {
        $this->expectException(InvalidInterval::class);
        $this->expectExceptionMessageIsOrContains('A span ends after it starts: 2026-11-10T09:00:00-05:00 does not come after 2026-11-10T09:00:00-05:00.');

        $this->span('2026-11-10 09:00', '2026-11-10 09:00');
    }

    public function testItRefusesAnEndBeforeItsStart(): void
    {
        $this->expectException(InvalidInterval::class);

        $this->span('2026-11-10 09:00', '2026-11-10 08:59');
    }

    public function testItIsBuiltFromTimestampsInATimeZone(): void
    {
        $span = Interval::ofTimestamps(1_700_000_000, 1_700_003_600, new DateTimeZone('Europe/Paris'));

        self::assertSame('2023-11-14T23:13:20+01:00', $span->start->format(\DATE_ATOM));
        self::assertSame('2023-11-15T00:13:20+01:00', $span->end->format(\DATE_ATOM));
        self::assertSame('Europe/Paris', $span->end->getTimezone()->getName());
    }

    public function testSpansThatTouchDoNotOverlap(): void
    {
        $morning = $this->span('2026-11-10 09:00', '2026-11-10 10:00');

        self::assertFalse($morning->overlaps($this->span('2026-11-10 10:00', '2026-11-10 11:00')));
        self::assertFalse($this->span('2026-11-10 08:00', '2026-11-10 09:00')->overlaps($morning));
        self::assertFalse($morning->overlaps($this->span('2026-11-10 08:00', '2026-11-10 09:00')));
        self::assertTrue($morning->overlaps($this->span('2026-11-10 09:59', '2026-11-10 11:00')));
        self::assertTrue($morning->overlaps($this->span('2026-11-10 08:00', '2026-11-10 09:01')));
        self::assertTrue($morning->overlaps($this->span('2026-11-10 09:15', '2026-11-10 09:30')));
    }

    public function testItCoversASpanWithinIt(): void
    {
        $day = $this->span('2026-11-10 09:00', '2026-11-10 17:00');

        self::assertTrue($day->covers($day));
        self::assertTrue($day->covers($this->span('2026-11-10 10:00', '2026-11-10 11:00')));
        self::assertFalse($day->covers($this->span('2026-11-10 08:59', '2026-11-10 11:00')));
        self::assertFalse($day->covers($this->span('2026-11-10 16:00', '2026-11-10 17:01')));
    }

    public function testItWidensByMinutes(): void
    {
        $span = $this->span('2026-11-10 09:00', '2026-11-10 10:00');

        self::assertSame($span, $span->widen(0, 0));
        $wider = $span->widen(15, 30);
        self::assertSame('08:45', $wider->start->format('H:i'));
        self::assertSame('10:30', $wider->end->format('H:i'));
        self::assertSame('09:00', $span->widen(0, 5)->start->format('H:i'));
        self::assertSame('10:05', $span->widen(0, 5)->end->format('H:i'));
        self::assertSame('08:55', $span->widen(5, 0)->start->format('H:i'));
        self::assertSame('10:00', $span->widen(5, 0)->end->format('H:i'));
    }
}
