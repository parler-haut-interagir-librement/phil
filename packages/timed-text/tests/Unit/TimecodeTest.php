<?php

declare(strict_types=1);

namespace Phil\TimedText\Tests\Unit;

use Phil\TimedText\Exception\InvalidCue;
use Phil\TimedText\Timecode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Timecode::class)]
final class TimecodeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?int}>
     */
    public static function webVttTimestamps(): iterable
    {
        yield 'hours' => ['01:02:03.004', 3_723_004];
        yield 'without hours' => ['02:03.004', 123_004];
        yield 'many hours' => ['9999:59:59.999', Timecode::MAX];
        yield 'one hour digit' => ['1:02:03.004', null];
        yield 'five hour digits' => ['10000:00:00.000', null];
        yield 'minutes past 59' => ['00:60:00.000', null];
        yield 'seconds past 59' => ['00:00:60.000', null];
        yield 'two fraction digits' => ['00:00:01.00', null];
        yield 'comma' => ['00:00:01,000', null];
        yield 'trailing line feed' => ["00:00:01.000\n", null];
        yield 'leading text' => ['x00:00:01.000', null];
    }

    #[DataProvider('webVttTimestamps')]
    public function testItReadsWebVttTimestamps(string $text, ?int $expected): void
    {
        self::assertSame($expected, Timecode::parseWebVtt($text));
    }

    /**
     * @return iterable<string, array{string, ?int}>
     */
    public static function srtTimestamps(): iterable
    {
        yield 'comma' => ['01:02:03,004', 3_723_004];
        yield 'full stop' => ['01:02:03.004', 3_723_004];
        yield 'short digits' => ['1:2:3,5', 3_723_500];
        yield 'two fraction digits' => ['00:00:01,25', 1_250];
        yield 'no fraction' => ['00:00:01', null];
        yield 'minutes past 59' => ['00:61:00,000', null];
        yield 'leading text' => ['x0:00:01,000', null];
        yield 'trailing line feed' => ["00:00:01,000\n", null];
    }

    #[DataProvider('srtTimestamps')]
    public function testItReadsSrtTimestamps(string $text, ?int $expected): void
    {
        self::assertSame($expected, Timecode::parseSrt($text));
    }

    /**
     * @return iterable<string, array{string, ?int}>
     */
    public static function clocks(): iterable
    {
        yield 'minutes' => ['2:03', 123_000];
        yield 'hours' => ['1:02:03', 3_723_000];
        yield 'milliseconds' => ['0:01.5', 1_500];
        yield 'comma' => ['0:01,25', 1_250];
        yield 'spaces' => [' 0:07 ', 7_000];
        yield 'seconds alone' => ['7', null];
        yield 'seconds past 59' => ['0:60', null];
        yield 'leading text' => ['at 0:07', null];
        yield 'trailing text' => ['0:07 s', null];
    }

    #[DataProvider('clocks')]
    public function testItReadsClocks(string $text, ?int $expected): void
    {
        self::assertSame($expected, Timecode::parseClock($text));
    }

    /**
     * @return iterable<string, array{int|float, ?int}>
     */
    public static function seconds(): iterable
    {
        yield 'integer' => [2, 2_000];
        yield 'decimal' => [1.5, 1_500];
        yield 'rounded down' => [0.0004, 0];
        yield 'rounded up' => [0.0005, 1];
        yield 'zero' => [0, 0];
        yield 'negative' => [-0.001, null];
        yield 'past the last instant' => [35_999_999.9991, null];
        yield 'the last instant' => [35_999_999.999, Timecode::MAX];
        yield 'infinite' => [\INF, null];
        yield 'not a number' => [\NAN, null];
    }

    #[DataProvider('seconds')]
    public function testItReadsSeconds(int|float $seconds, ?int $expected): void
    {
        self::assertSame($expected, Timecode::fromSeconds($seconds));
    }

    public function testItWritesSeconds(): void
    {
        self::assertSame(3, Timecode::toSeconds(3_000));
        self::assertSame(0, Timecode::toSeconds(0));
        self::assertSame(3.25, Timecode::toSeconds(3_250));
        self::assertSame(0.001, Timecode::toSeconds(1));
    }

    public function testItWritesTimestamps(): void
    {
        self::assertSame('01:02:03.004', Timecode::formatWebVtt(3_723_004));
        self::assertSame('00:00:00.000', Timecode::formatWebVtt(0));
        self::assertSame('9999:59:59.999', Timecode::formatWebVtt(Timecode::MAX));
        self::assertSame('01:02:03,004', Timecode::formatSrt(3_723_004));
        self::assertSame('00:59:59,999', Timecode::formatSrt(3_599_999));
    }

    public function testItWritesClocks(): void
    {
        self::assertSame('0:00', Timecode::formatClock(999));
        self::assertSame('2:03', Timecode::formatClock(123_456));
        self::assertSame('59:59', Timecode::formatClock(3_599_999));
        self::assertSame('1:00:00', Timecode::formatClock(3_600_000));
        self::assertSame('10:02:03', Timecode::formatClock(36_123_000));
    }

    public function testItWritesAndReadsDurations(): void
    {
        self::assertSame('PT75.5S', Timecode::formatDuration(75_500));
        self::assertSame('PT0S', Timecode::formatDuration(0));
        self::assertSame(75_500, Timecode::parseDuration('PT75.5S'));
        self::assertSame(3_723_004, Timecode::parseDuration('PT1H2M3.004S'));
        self::assertSame(120_000, Timecode::parseDuration('PT2M'));
        self::assertSame(3_600_000, Timecode::parseDuration('PT1H'));
        self::assertSame(1_250, Timecode::parseDuration('PT1.25S'));
        self::assertNull(Timecode::parseDuration('PT'));
        self::assertNull(Timecode::parseDuration('P1D'));
        self::assertNull(Timecode::parseDuration('PT1.5S '));
        self::assertNull(Timecode::parseDuration('xPT1S'));
        self::assertNull(Timecode::parseDuration('PT9999H60M'));
        self::assertSame(Timecode::MAX, Timecode::parseDuration('PT9999H59M59.999S'));
    }

    public function testItRefusesInstantsOutOfRange(): void
    {
        Timecode::check(0);
        Timecode::check(Timecode::MAX);

        $this->expectException(InvalidCue::class);
        $this->expectExceptionMessageIsOrContains('-1 ms is not an instant between 0 and 35999999999 ms.');

        Timecode::formatWebVtt(-1);
    }

    public function testItRefusesInstantsPastTheLast(): void
    {
        $this->expectException(InvalidCue::class);

        Timecode::formatClock(Timecode::MAX + 1);
    }
}
