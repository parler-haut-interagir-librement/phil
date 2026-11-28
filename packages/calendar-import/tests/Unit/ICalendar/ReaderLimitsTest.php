<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Tests\Unit\ICalendar;

use Phil\CalendarImport\Exception\InvalidLimits;
use Phil\CalendarImport\ICalendar\ReaderLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReaderLimits::class)]
#[CoversClass(InvalidLimits::class)]
final class ReaderLimitsTest extends TestCase
{
    public function testTheDefaultsAndTheBoundsAreKept(): void
    {
        $limits = new ReaderLimits();
        self::assertSame([5_242_880, 5_000, 16_384, 6, 20_000], [$limits->bytes, $limits->events, $limits->lineBytes, $limits->depth, $limits->components]);

        $low = new ReaderLimits(64, 1, 75, 2, 2);
        self::assertSame([64, 1, 75, 2, 2], [$low->bytes, $low->events, $low->lineBytes, $low->depth, $low->components]);

        $high = new ReaderLimits(ReaderLimits::MAX_BYTES, ReaderLimits::MAX_EVENTS, ReaderLimits::MAX_LINE_BYTES, ReaderLimits::MAX_DEPTH, ReaderLimits::MAX_COMPONENTS);
        self::assertSame([67_108_864, 100_000, 1_048_576, 16, 200_000], [$high->bytes, $high->events, $high->lineBytes, $high->depth, $high->components]);
    }

    /**
     * @return iterable<string, array{array<string, int>, string}>
     */
    public static function outOfRange(): iterable
    {
        yield 'bytes low' => [['bytes' => 63], 'The bytes of a calendar is 64 to 67108864, 63 given.'];
        yield 'bytes high' => [['bytes' => ReaderLimits::MAX_BYTES + 1], 'The bytes of a calendar is 64 to 67108864, 67108865 given.'];
        yield 'events low' => [['events' => 0], 'The events of a calendar is 1 to 100000, 0 given.'];
        yield 'events high' => [['events' => 100_001], 'The events of a calendar is 1 to 100000, 100001 given.'];
        yield 'line low' => [['lineBytes' => 74], 'The bytes of a line is 75 to 1048576, 74 given.'];
        yield 'line high' => [['lineBytes' => 1_048_577], 'The bytes of a line is 75 to 1048576, 1048577 given.'];
        yield 'depth low' => [['depth' => 1], 'The depth of a calendar is 2 to 16, 1 given.'];
        yield 'depth high' => [['depth' => 17], 'The depth of a calendar is 2 to 16, 17 given.'];
        yield 'components low' => [['components' => 1], 'The components of a calendar is 2 to 200000, 1 given.'];
        yield 'components high' => [['components' => 200_001], 'The components of a calendar is 2 to 200000, 200001 given.'];
    }

    /**
     * @param array<string, int> $arguments
     */
    #[DataProvider('outOfRange')]
    public function testABoundOutOfItsRangeIsRefused(array $arguments, string $message): void
    {
        $this->expectException(InvalidLimits::class);
        $this->expectExceptionMessageIsOrContains($message);

        new ReaderLimits(...$arguments);
    }
}
