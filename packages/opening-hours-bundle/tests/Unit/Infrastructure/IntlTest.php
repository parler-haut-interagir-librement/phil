<?php

declare(strict_types=1);

namespace Phil\OpeningHoursBundle\Tests\Unit\Infrastructure;

use IntlDateFormatter;
use Locale;
use Override;
use Phil\OpeningHours\DayOfWeek;
use Phil\OpeningHours\TimeOfDay;
use Phil\OpeningHoursBundle\Infrastructure\Intl\Intl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(Intl::class)]
final class IntlTest extends TestCase
{
    private string $locale;

    #[Override]
    protected function setUp(): void
    {
        $this->locale = Locale::getDefault();
        new ReflectionProperty(Intl::class, 'formatters')->setValue(null, []);
    }

    #[Override]
    protected function tearDown(): void
    {
        Locale::setDefault($this->locale);
    }

    public function testEachLanguageAndStyleHasItsOwnFormatter(): void
    {
        self::assertSame(['lundi', 'Monday', 'lundi'], [Intl::day(DayOfWeek::Monday, 'fr_CA'), Intl::day(DayOfWeek::Monday, 'en_US'), Intl::day(DayOfWeek::Monday, 'fr_CA')]);
        self::assertSame(['25 déc. 2026', 'Dec 25, 2026'], [Intl::date('2026-12-25', 'fr_CA'), Intl::date('2026-12-25', 'en_US')]);
        self::assertSame(['09 h 00', '00 h 00'], [Intl::time(TimeOfDay::of(9, 0), 'fr_CA'), Intl::time(TimeOfDay::of(24, 0), 'fr_CA')]);
        self::assertSame('9:00 AM', str_replace("\u{202F}", ' ', Intl::time(TimeOfDay::of(9, 0), 'en_US')));
    }

    public function testWithoutLanguageTheDefaultOneIsReadEachTime(): void
    {
        Locale::setDefault('en_US');
        self::assertSame('Monday', Intl::day(DayOfWeek::Monday, null));

        Locale::setDefault('fr_CA');
        self::assertSame('lundi', Intl::day(DayOfWeek::Monday, null));
    }

    public function testManyLanguagesStillWriteTheirOwnWords(): void
    {
        foreach (range(1, 100) as $number) {
            Intl::day(DayOfWeek::Monday, 'en_'.$number);
        }

        self::assertSame(['Montag', 'lunes', 'lundi'], [Intl::day(DayOfWeek::Monday, 'de'), Intl::day(DayOfWeek::Monday, 'es'), Intl::day(DayOfWeek::Monday, 'fr_CA')]);
    }

    public function testAFormatterIsBuiltOnceAndKeptForItsLanguageAndStyle(): void
    {
        Intl::day(DayOfWeek::Monday, 'fr_CA');
        $day = $this->kept()['fr_CA'][IntlDateFormatter::NONE][IntlDateFormatter::NONE];
        Intl::day(DayOfWeek::Tuesday, 'fr_CA');
        Intl::date('2026-12-25', 'fr_CA');
        Intl::time(TimeOfDay::of(9, 0), 'fr_CA');

        $kept = $this->kept();
        self::assertSame($day, $kept['fr_CA'][IntlDateFormatter::NONE][IntlDateFormatter::NONE]);
        self::assertSame([IntlDateFormatter::NONE, IntlDateFormatter::MEDIUM], array_keys($kept['fr_CA']));
        self::assertSame([IntlDateFormatter::NONE, IntlDateFormatter::SHORT], array_keys($kept['fr_CA'][IntlDateFormatter::NONE]));
        self::assertSame('EEEE', $day->getPattern());
    }

    public function testTheFormattersOfSixtyFourLanguagesAreKeptThenBuiltAgain(): void
    {
        $locales = array_map(static fn (int $number): string => 'en_'.$number, range(1, 64));

        foreach ($locales as $locale) {
            Intl::day(DayOfWeek::Monday, $locale);
        }

        self::assertSame($locales, array_keys($this->kept()));

        Intl::date('2026-12-25', 'en_1');
        self::assertCount(64, $this->kept());

        Intl::day(DayOfWeek::Monday, 'en_65');
        self::assertSame(['en_65'], array_keys($this->kept()));
    }

    /**
     * @return array<string, array<int, array<int, IntlDateFormatter>>>
     */
    private function kept(): array
    {
        /** @var array<string, array<int, array<int, IntlDateFormatter>>> */
        return new ReflectionProperty(Intl::class, 'formatters')->getValue();
    }
}
