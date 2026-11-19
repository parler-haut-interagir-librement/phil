<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Tests\Unit;

use InvalidArgumentException;
use Phil\UsageMeter\Exception\InvalidUsage;
use Phil\UsageMeter\Exception\UnknownMeter;
use Phil\UsageMeter\Exception\UsageMeterException;
use Phil\UsageMeter\Limit;
use Phil\UsageMeter\Meter;
use Phil\UsageMeter\Meters;
use Phil\UsageMeter\Period\CalendarPeriod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Meter::class)]
#[CoversClass(Meters::class)]
#[CoversClass(UnknownMeter::class)]
final class MetersTest extends TestCase
{
    public function testAMeterKeepsItsPeriodAndLimit(): void
    {
        $period = CalendarPeriod::month();
        $meter = new Meter('exports', $period, Limit::of(5));

        self::assertSame('exports', $meter->name);
        self::assertSame($period, $meter->period);
        self::assertSame(5, $meter->limit->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validNames(): iterable
    {
        yield 'letters' => ['exports'];
        yield 'digit first' => ['3d-prints'];
        yield 'punctuation' => ['ai.tokens_in-out'];
        yield 'sixty-four characters' => [str_repeat('a', 64)];
    }

    #[DataProvider('validNames')]
    public function testAMeterNameIsLowercaseLettersDigitsAndPunctuation(string $name): void
    {
        self::assertSame($name, new Meter($name, CalendarPeriod::month(), Limit::of(1))->name);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Exports'];
        yield 'punctuation first' => ['-exports'];
        yield 'colon' => ['ai:tokens'];
        yield 'space' => ['ai tokens'];
        yield 'sixty-five characters' => [str_repeat('a', 65)];
        yield 'trailing newline' => ["exports\n"];
    }

    #[DataProvider('invalidNames')]
    public function testAnyOtherNameIsRefused(string $name): void
    {
        $this->expectException(InvalidUsage::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a meter name: up to 64 lowercase letters, digits, "_", "." or "-", starting with a letter or a digit.', $name));

        new Meter($name, CalendarPeriod::month(), Limit::of(1));
    }

    public function testMetersAreFoundByName(): void
    {
        $exports = new Meter('exports', CalendarPeriod::month(), Limit::of(5));
        $projects = new Meter('123', CalendarPeriod::year(), Limit::of(3));
        $meters = new Meters($exports, $projects);

        self::assertSame($exports, $meters->get('exports'));
        self::assertSame($projects, $meters->get('123'));
        self::assertTrue($meters->has('exports'));
        self::assertFalse($meters->has('imports'));
        self::assertSame(['exports', '123'], $meters->names());
    }

    public function testAnUnknownMeterIsAnError(): void
    {
        try {
            new Meters()->get('exports');
            self::fail('An unknown meter was found.');
        } catch (UnknownMeter $unknownMeter) {
            self::assertSame('No meter is named "exports".', $unknownMeter->getMessage());
            self::assertInstanceOf(UsageMeterException::class, $unknownMeter);
            self::assertInstanceOf(InvalidArgumentException::class, $unknownMeter);
        }
    }

    public function testAMeterIsDeclaredOnce(): void
    {
        $this->expectException(InvalidUsage::class);
        $this->expectExceptionMessageIsOrContains('The meter "exports" is declared twice.');

        new Meters(new Meter('exports', CalendarPeriod::month(), Limit::of(5)), new Meter('exports', CalendarPeriod::day(), Limit::of(1)));
    }
}
