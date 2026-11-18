<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Unit\Application;

use Phil\IncidentMode\Duration;
use Phil\IncidentMode\Exception\InvalidIncident;
use Phil\IncidentModeBundle\Application\DurationInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DurationInput::class)]
final class DurationInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function written(): iterable
    {
        yield 'minutes alone' => ['90', 5_400];
        yield 'one minute' => ['1', 60];
        yield 'm' => ['45m', 2_700];
        yield 'min' => ['45 min', 2_700];
        yield 'mins' => ['45mins', 2_700];
        yield 'minute' => ['1 minute', 60];
        yield 'minutes' => ['30 minutes', 1_800];
        yield 'h' => ['2h', 7_200];
        yield 'hour' => ['1 hour', 3_600];
        yield 'hours' => ['3 hours', 10_800];
        yield 'd' => ['3d', 259_200];
        yield 'day' => ['1 day', 86_400];
        yield 'days' => ['2 days', 172_800];
        yield 'parts' => ['1d 12h', 129_600];
        yield 'parts without spaces' => ['1d12h30m', 131_400];
        yield 'upper case and spaces around' => ['  2H  ', 7_200];
        yield 'eight parts' => ['1m1m1m1m1m1m1m1m', 480];
        yield 'ninety days' => ['90d', 7_776_000];
        yield 'leading zeros' => ['007m', 420];
        yield 'a new line between parts' => ["2h\n3m", 7_380];
    }

    #[DataProvider('written')]
    public function testADurationIsRead(string $text, int $seconds): void
    {
        self::assertSame($seconds, DurationInput::parse($text)->seconds);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'empty' => [''];
        yield 'zero' => ['0'];
        yield 'under a minute' => ['59s'];
        yield 'seconds' => ['90s'];
        yield 'past ninety days' => ['91d'];
        yield 'negative' => ['-5m'];
        yield 'a unit alone' => ['h'];
        yield 'seven digits' => ['1234567'];
        yield 'seven digits with a unit' => ['1234567m'];
        yield 'nine parts' => ['1m1m1m1m1m1m1m1m1m'];
        yield 'a word' => ['soon'];
        yield 'a fraction' => ['1.5h'];
        yield 'text after' => ['2h please'];
        yield 'text before' => ['in 2h'];
        yield 'too long' => [str_repeat(' ', 30).'1m'.str_repeat(' ', 30).'x'];
    }

    #[DataProvider('refused')]
    public function testWhatIsNoDurationIsRefused(string $text): void
    {
        $this->expectException(InvalidIncident::class);
        DurationInput::parse($text);
    }

    public function testTheTextIsBoundedBeforeItIsTrimmed(): void
    {
        self::assertSame(60, DurationInput::parse(str_repeat(' ', 100).'1m')->seconds);

        try {
            DurationInput::parse(str_repeat('1', 65));
            self::fail('A text longer than 64 characters is refused.');
        } catch (InvalidIncident $invalidIncident) {
            self::assertStringContainsString('at most 64 characters', $invalidIncident->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('is not a duration');
        DurationInput::parse(str_repeat('1', 64));
    }

    public function testAnAmountOfAUnit(): void
    {
        self::assertSame(120, DurationInput::ofAmount(2, DurationInput::MINUTES)->seconds);
        self::assertSame(7_200, DurationInput::ofAmount(2, DurationInput::HOURS)->seconds);
        self::assertSame(172_800, DurationInput::ofAmount(2, DurationInput::DAYS)->seconds);
        self::assertSame(Duration::MAXIMUM, DurationInput::ofAmount(90, DurationInput::DAYS)->seconds);
        self::assertSame(Duration::MAXIMUM, DurationInput::ofAmount(129_600, DurationInput::MINUTES)->seconds);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function refusedAmounts(): iterable
    {
        yield 'zero' => [0, DurationInput::HOURS];
        yield 'negative' => [-1, DurationInput::HOURS];
        yield 'one more day' => [91, DurationInput::DAYS];
        yield 'one more minute' => [129_601, DurationInput::MINUTES];
        yield 'an overflow' => [\PHP_INT_MAX, DurationInput::DAYS];
        yield 'an unknown unit' => [2, 'weeks'];
    }

    #[DataProvider('refusedAmounts')]
    public function testAnAmountOutOfBoundsIsRefused(int $amount, string $unit): void
    {
        $this->expectException(InvalidIncident::class);
        DurationInput::ofAmount($amount, $unit);
    }

    public function testAnAmountOneSecondOverTheMaximumIsTold(): void
    {
        $this->expectExceptionMessageIsOrContains(\sprintf('%d was given', Duration::MAXIMUM + 1));
        DurationInput::ofAmount(91, DurationInput::DAYS);
    }

    public function testTheUnknownUnitIsNamed(): void
    {
        $this->expectExceptionMessageIsOrContains('"weeks" is not a unit of duration');
        DurationInput::ofAmount(2, 'weeks');
    }

    /**
     * @return iterable<string, array{int, int, string, string}>
     */
    public static function splits(): iterable
    {
        yield 'days' => [172_800, 2, DurationInput::DAYS, '2d'];
        yield 'hours' => [129_600, 36, DurationInput::HOURS, '36h'];
        yield 'one hour' => [3_600, 1, DurationInput::HOURS, '1h'];
        yield 'minutes' => [5_400, 90, DurationInput::MINUTES, '90m'];
        yield 'seconds left over' => [90, 1, DurationInput::MINUTES, '1m'];
    }

    #[DataProvider('splits')]
    public function testADurationIsSplitInItsLargestWholeUnit(int $seconds, int $amount, string $unit, string $text): void
    {
        $duration = Duration::ofSeconds($seconds);

        self::assertSame(['amount' => $amount, 'unit' => $unit], DurationInput::split($duration));
        self::assertSame($text, DurationInput::format($duration));
    }
}
