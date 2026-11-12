<?php

declare(strict_types=1);

namespace Phil\Almanac\Tests\Unit\Rule;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Almanac\Almanac;
use Phil\Almanac\Astronomy\LunarEclipseKind;
use Phil\Almanac\Astronomy\SolarEclipseKind;
use Phil\Almanac\Definition;
use Phil\Almanac\Exception\InvalidRule;
use Phil\Almanac\Rule\EclipseRule;
use Phil\Almanac\Rule\RuleParser;
use Phil\Almanac\Rule\WithinTheYear;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(EclipseRule::class)]
#[CoversClass(RuleParser::class)]
final class EclipseRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function rules(): iterable
    {
        yield 'every solar eclipse' => ['Solar  Eclipse', 'solar eclipse', ['2026-02-17 07:12', '2026-08-12 13:46']];
        yield 'total solar eclipses' => ['total solar eclipse', 'total solar eclipse', ['2026-08-12 13:46']];
        yield 'annular solar eclipses' => ['annular solar eclipse', 'annular solar eclipse', ['2026-02-17 07:12']];
        yield 'no hybrid solar eclipse in 2026' => ['hybrid solar eclipse', 'hybrid solar eclipse', []];
        yield 'no partial solar eclipse in 2026' => ['PARTIAL solar eclipse', 'partial solar eclipse', []];
        yield 'every lunar eclipse' => ['lunar eclipse', 'lunar eclipse', ['2026-03-03 06:34', '2026-08-28 00:13']];
        yield 'total lunar eclipses' => ['total lunar eclipse', 'total lunar eclipse', ['2026-03-03 06:34']];
        yield 'partial lunar eclipses' => ['partial lunar eclipse', 'partial lunar eclipse', ['2026-08-28 00:13']];
        yield 'no penumbral lunar eclipse in 2026' => ['penumbral lunar eclipse', 'penumbral lunar eclipse', []];
    }

    /**
     * @param list<string> $dates in Toronto, to the minute
     */
    #[DataProvider('rules')]
    public function testARuleIsReadWrittenAndFound(string $text, string $canonical, array $dates): void
    {
        $zone = new DateTimeZone('America/Toronto');
        $rule = RuleParser::parse($text);

        self::assertInstanceOf(EclipseRule::class, $rule);
        self::assertInstanceOf(WithinTheYear::class, $rule);
        self::assertSame($canonical, (string) $rule);
        self::assertEquals($rule, RuleParser::parse($canonical));
        self::assertTrue($rule->isInstant());

        $found = $rule->in(2026, $zone);
        self::assertSame($dates, array_map(static fn (DateTimeImmutable $at): string => $at->format('Y-m-d H:i'), $found));

        foreach ($found as $at) {
            self::assertSame('America/Toronto', $at->getTimezone()->getName());
        }
    }

    public function testTheFactories(): void
    {
        self::assertTrue(EclipseRule::solar()->solar);
        self::assertNull(EclipseRule::solar()->kind);
        self::assertSame(SolarEclipseKind::Hybrid, EclipseRule::solar(SolarEclipseKind::Hybrid)->kind);
        self::assertFalse(EclipseRule::lunar()->solar);
        self::assertSame('penumbral lunar eclipse', (string) EclipseRule::lunar(LunarEclipseKind::Penumbral));
        self::assertSame('2023-04-20', EclipseRule::solar(SolarEclipseKind::Hybrid)->in(2023, new DateTimeZone('UTC'))[0]->format('Y-m-d'));
    }

    /**
     * @return iterable<array{string}>
     */
    public static function unreadable(): iterable
    {
        yield ['eclipse'];
        yield ['penumbral solar eclipse'];
        yield ['annular lunar eclipse'];
        yield ['total eclipse'];
        yield ['solar eclipse of the heart'];
        yield ['lunar eclipse of the heart'];
    }

    #[DataProvider('unreadable')]
    public function testAnUnknownEclipseIsRefused(string $text): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a rule the almanac reads.', $text));

        RuleParser::parse($text);
    }

    /**
     * The year of an eclipse runs from January 1 to its end: an eclipse of the first day belongs to it, one of the last
     * day to the year it ends.
     */
    public function testTheEclipsesAtTheEdgesOfAYear(): void
    {
        $utc = new DateTimeZone('UTC');
        $dates = static fn (int $year): array => array_map(static fn (DateTimeImmutable $at): string => $at->format('Y-m-d H:i'), EclipseRule::lunar()->in($year, $utc));

        self::assertSame(['2009-02-09 14:38', '2009-07-07 09:38', '2009-08-06 00:39', '2009-12-31 19:23'], $dates(2009));
        self::assertSame(['2048-01-01 06:52', '2048-06-26 02:01', '2048-12-20 06:27'], $dates(2048));
        self::assertSame(['2018-01-31 13:29', '2018-07-27 20:22'], $dates(2018));
        self::assertSame([], array_filter($dates(2029), static fn (string $date): bool => str_starts_with($date, '2028')));
    }

    /**
     * An eclipse is an instant: the almanac shows it at its time, or on its day.
     */
    public function testAnEclipseInTheAlmanac(): void
    {
        $zone = new DateTimeZone('Europe/Paris');
        $almanac = new Almanac([
            new Definition('solar-eclipse', 'Solar eclipse', RuleParser::parse('solar eclipse'), allDay: false),
            new Definition('lunar-eclipse', 'Lunar eclipse', RuleParser::parse('lunar eclipse')),
        ], $zone);

        $dates = $almanac->between(new DateTimeImmutable('2026-08-01', $zone), new DateTimeImmutable('2026-09-01', $zone));

        self::assertCount(2, $dates);
        self::assertSame('2026-08-12 19:46', $dates[0]->span->start->format('Y-m-d H:i'));
        self::assertFalse($dates[0]->span->allDay);
        self::assertSame('2026-08-28 00:00', $dates[1]->span->start->format('Y-m-d H:i'));
        self::assertTrue($dates[1]->span->allDay);
    }
}
