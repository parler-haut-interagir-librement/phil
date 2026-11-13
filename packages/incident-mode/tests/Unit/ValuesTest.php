<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Tests\Unit;

use DateTimeImmutable;
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\Exception\InvalidIncident;
use Phil\IncidentMode\Restriction;
use Phil\IncidentMode\Tests\Fixture\Refusals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Restriction::class)]
#[CoversClass(Duration::class)]
#[CoversClass(Actor::class)]
#[CoversClass(InvalidIncident::class)]
final class ValuesTest extends TestCase
{
    use Refusals;

    public function testARestrictionIsNamed(): void
    {
        self::assertSame('signups', Restriction::named('signups')->name);
        self::assertSame('a', Restriction::named('a')->name);
        self::assertSame('a'.str_repeat('b', 63), Restriction::named('a'.str_repeat('b', 63))->name);
        self::assertTrue(Restriction::named(Restriction::READ_ONLY)->isReadOnly());
        self::assertFalse(Restriction::named(Restriction::SIGNUPS)->isReadOnly());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badNames(): iterable
    {
        yield 'empty' => [''];
        yield 'a digit first' => ['1abc'];
        yield 'an uppercase letter' => ['Signups'];
        yield 'too long' => ['a'.str_repeat('b', 64)];
        yield 'a space' => ['new posts'];
        yield 'a newline at the end' => ["signups\n"];
    }

    #[DataProvider('badNames')]
    public function testARestrictionNameIsRefused(string $name): void
    {
        self::assertRefused(InvalidIncident::class, 'is not a restriction name', static fn (): mixed => Restriction::named($name));
    }

    public function testADurationIsBounded(): void
    {
        self::assertSame(60, Duration::ofSeconds(60)->seconds);
        self::assertSame(Duration::MAXIMUM, Duration::ofSeconds(Duration::MAXIMUM)->seconds);
        self::assertSame(120, Duration::ofMinutes(2)->seconds);
        self::assertSame(7_200, Duration::ofHours(2)->seconds);
        self::assertSame(172_800, Duration::ofDays(2)->seconds);
        self::assertSame(90 * 86_400, Duration::ofDays(90)->seconds);
    }

    /**
     * @return iterable<string, array{callable(): Duration}>
     */
    public static function badDurations(): iterable
    {
        yield 'under a minute' => [static fn (): Duration => Duration::ofSeconds(59)];
        yield 'zero' => [static fn (): Duration => Duration::ofMinutes(0)];
        yield 'negative' => [static fn (): Duration => Duration::ofHours(-1)];
        yield 'over ninety days' => [static fn (): Duration => Duration::ofSeconds(Duration::MAXIMUM + 1)];
        yield 'ninety-one days' => [static fn (): Duration => Duration::ofDays(91)];
        yield 'an overflow' => [static fn (): Duration => Duration::ofDays(\PHP_INT_MAX)];
        yield 'an overflow below' => [static fn (): Duration => Duration::ofDays(\PHP_INT_MIN)];
        yield 'one over the limit of the multiplication' => [static fn (): Duration => Duration::ofMinutes(intdiv(\PHP_INT_MAX, 60) + 1)];
        yield 'one under the limit of the multiplication' => [static fn (): Duration => Duration::ofMinutes(intdiv(\PHP_INT_MIN, 60) - 1)];
    }

    /**
     * @param callable(): Duration $duration
     */
    #[DataProvider('badDurations')]
    public function testADurationOutOfBoundsIsRefused(callable $duration): void
    {
        self::assertRefused(InvalidIncident::class, 'An incident lasts from 60 seconds to 7776000 seconds', static fn (): mixed => $duration());
    }

    public function testTheMultiplicationAtItsLimitIsRefusedByTheBoundsOnly(): void
    {
        self::assertRefused(InvalidIncident::class, \sprintf('%d was given', intdiv(\PHP_INT_MAX, 60) * 60), static fn (): mixed => Duration::ofMinutes(intdiv(\PHP_INT_MAX, 60)));
    }

    public function testTheMultiplicationAtItsLowerLimitIsRefusedByTheBoundsOnly(): void
    {
        self::assertRefused(InvalidIncident::class, \sprintf('%d was given', intdiv(\PHP_INT_MIN, 60) * 60), static fn (): mixed => Duration::ofMinutes(intdiv(\PHP_INT_MIN, 60)));
    }

    public function testADurationEndsAfterItsStart(): void
    {
        $start = new DateTimeImmutable('2026-11-13 10:00:00 UTC');

        self::assertSame('2026-11-13 12:00:00', Duration::ofHours(2)->after($start)->format('Y-m-d H:i:s'));
        self::assertTrue(Duration::ofHours(2)->isLongerThan(Duration::ofMinutes(119)));
        self::assertFalse(Duration::ofHours(2)->isLongerThan(Duration::ofMinutes(120)));
    }

    public function testAnActorIsAStableIdentifier(): void
    {
        self::assertSame('0f6c3f2e-member', Actor::of('0f6c3f2e-member')->id);
        self::assertSame('a@b', Actor::of('a@b')->id);
        self::assertSame('a@b.c@d', Actor::of('a@b.c@d')->id);
        self::assertSame('a@b@c.d', Actor::of('a@b@c.d')->id);
        self::assertSame(str_repeat('x', 190), Actor::of(str_repeat('x', 190))->id);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badActors(): iterable
    {
        yield 'empty' => ['', 'visible characters'];
        yield 'too long' => [str_repeat('x', 191), 'visible characters'];
        yield 'a space' => ['a b', 'visible characters'];
        yield 'a control character' => ["a\x7F", 'visible characters'];
        yield 'a null byte' => ["a\x00", 'visible characters'];
        yield 'an e-mail address' => ['member@example.org', 'never an e-mail address'];
        yield 'an e-mail address with a newline after' => ["member@example.org\n", 'visible characters'];
    }

    #[DataProvider('badActors')]
    public function testAnActorIsRefused(string $id, string $message): void
    {
        self::assertRefused(InvalidIncident::class, $message, static fn (): mixed => Actor::of($id));
    }
}
