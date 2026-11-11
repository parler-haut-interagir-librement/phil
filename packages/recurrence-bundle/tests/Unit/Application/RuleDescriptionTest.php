<?php

declare(strict_types=1);

namespace Phil\RecurrenceBundle\Tests\Unit\Application;

use DateTimeZone;
use IntlDateFormatter;
use Locale;
use Phil\Recurrence\RecurrenceRule;
use Phil\RecurrenceBundle\Application\RuleDescription;
use Phil\RecurrenceBundle\Tests\Unit\Fixtures\Translations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(RuleDescription::class)]
final class RuleDescriptionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function rules(): iterable
    {
        yield 'daily' => ['FREQ=DAILY', 'Every day', 'Tous les jours'];
        yield 'every other week on two days, so many times' => ['FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE;COUNT=10', 'Every 2 weeks on Monday and Wednesday, 10 times', 'Toutes les 2 semaines le lundi et le mercredi, 10 fois'];
        yield 'once' => ['FREQ=WEEKLY;BYDAY=FR;COUNT=1', 'Every week on Friday, once', 'Toutes les semaines le vendredi, une fois'];
        yield 'the second Tuesday until a day' => ['FREQ=MONTHLY;BYDAY=2TU;UNTIL=20261231', 'Every month on the second Tuesday, until 31 December 2026', 'Tous les mois le deuxième mardi, jusqu’au 31 décembre 2026'];
        yield 'month days from the start and the end' => ['FREQ=MONTHLY;INTERVAL=3;BYMONTHDAY=1,22,-1,-3', 'Every 3 months on the 3rd day from the end, the last day, the 1st and the 22nd', 'Tous les 3 mois le 3e jour en partant de la fin, le dernier jour, le 1er et le 22'];
        yield 'the last Friday of some months' => ['FREQ=YEARLY;BYMONTH=6,7;BYDAY=-1FR', 'Every year on the last Friday in June and July', 'Tous les ans le dernier vendredi en juin et juillet'];
        yield 'the second to last and the sixth' => ['FREQ=YEARLY;INTERVAL=2;BYDAY=-2SU,6MO', 'Every 2 years on the second to last Sunday and the 6th Monday', 'Tous les 2 ans l’avant-dernier dimanche et le 6e lundi'];
        yield 'far from the end' => ['FREQ=YEARLY;BYDAY=-3SA', 'Every year on the 3rd Saturday from the end', 'Tous les ans le 3e samedi en partant de la fin'];
        yield 'set positions' => ['FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1,1', 'Every month on Monday, Tuesday, Wednesday, Thursday and Friday keeping the last and the first', 'Tous les mois le lundi, le mardi, le mercredi, le jeudi et le vendredi en gardant le dernier et le premier'];
        yield 'the second to last day' => ['FREQ=MONTHLY;BYMONTHDAY=-2', 'Every month on the second to last day', 'Tous les mois l’avant-dernier jour'];
        yield 'the second to last set position' => ['FREQ=MONTHLY;BYDAY=SA,SU;BYSETPOS=-2,3', 'Every month on Saturday and Sunday keeping the second to last and the third', 'Tous les mois le samedi et le dimanche en gardant l’avant-dernier et le troisième'];
        yield 'the first Monday' => ['FREQ=MONTHLY;BYDAY=1MO', 'Every month on the first Monday', 'Tous les mois le premier lundi'];
        yield 'every other day' => ['FREQ=DAILY;INTERVAL=2', 'Every 2 days', 'Tous les 2 jours'];
        yield 'every month' => ['FREQ=MONTHLY', 'Every month', 'Tous les mois'];
        yield 'every year in a month' => ['FREQ=YEARLY;BYMONTH=12', 'Every year in December', 'Tous les ans en décembre'];
    }

    #[DataProvider('rules')]
    public function testARuleIsWrittenInWords(string $rule, string $english, string $french): void
    {
        $description = new RuleDescription(Translations::translator());

        self::assertSame($english, $description->describe(RecurrenceRule::parse($rule), 'en'));
        self::assertSame($french, $description->describe(RecurrenceRule::parse($rule), 'fr'));
        $default = Locale::getDefault();
        Locale::setDefault('fr');

        try {
            self::assertSame($english, $description->describe(RecurrenceRule::parse($rule)));
        } finally {
            Locale::setDefault($default);
        }
    }

    public function testAnEndTimeIsADayOfTheZoneGiven(): void
    {
        $description = new RuleDescription(Translations::translator());
        $rule = RecurrenceRule::parse('FREQ=WEEKLY;UNTIL=20270101T045959Z');

        self::assertSame('Every week, until 31 December 2026', $description->describe($rule, 'en', new DateTimeZone('America/Toronto')));
        self::assertSame('Every week, until 1 January 2027', $description->describe($rule, 'en'));
        self::assertSame('Every week, until 31 December 2026', $description->describe(RecurrenceRule::parse('FREQ=WEEKLY;UNTIL=20261231'), 'en', new DateTimeZone('Asia/Tokyo')));
    }

    /**
     * One description writes many rules: each language, pattern and time zone keeps its own formatter.
     */
    public function testOneDescriptionWritesInEachLanguageAndZone(): void
    {
        $description = new RuleDescription(Translations::translator());
        $rule = RecurrenceRule::parse('FREQ=YEARLY;BYMONTH=6;BYDAY=-1FR;UNTIL=20270101T045959Z');

        self::assertSame('Every year on the last Friday in June, until 1 January 2027', $description->describe($rule, 'en'));
        self::assertSame('Tous les ans le dernier vendredi en juin, jusqu’au 31 décembre 2026', $description->describe($rule, 'fr', new DateTimeZone('America/Toronto')));
        self::assertSame('Every year on the last Friday in June, until 31 December 2026', $description->describe($rule, 'en', new DateTimeZone('America/Toronto')));
        self::assertSame('Tous les ans le dernier vendredi en juin, jusqu’au 1 janvier 2027', $description->describe($rule, 'fr'));
    }

    public function testSixtyFourFormattersAreKeptThenBuiltAgain(): void
    {
        $description = new RuleDescription(Translations::translator());
        $rule = RecurrenceRule::parse('FREQ=DAILY;UNTIL=20261231T120000Z');
        $formatters = new ReflectionProperty(RuleDescription::class, 'formatters');
        $zones = \array_slice(DateTimeZone::listIdentifiers(), 0, 63);

        foreach ($zones as $zone) {
            $description->describe($rule, 'en', new DateTimeZone($zone));
        }

        $description->describe(RecurrenceRule::parse('FREQ=YEARLY;BYMONTH=12'), 'en');
        /** @var array<string, array<string, array<string, IntlDateFormatter>>> $kept */
        $kept = $formatters->getValue($description);
        self::assertSame($zones, array_keys($kept['en']['d MMMM y']));
        self::assertSame(['d MMMM y', 'MMMM'], array_keys($kept['en']));

        $first = $kept['en']['d MMMM y'][$zones[0]];
        $description->describe($rule, 'en', new DateTimeZone($zones[0]));
        /** @var array<string, array<string, array<string, IntlDateFormatter>>> $kept */
        $kept = $formatters->getValue($description);
        self::assertSame($first, $kept['en']['d MMMM y'][$zones[0]]);
        self::assertCount(63, $kept['en']['d MMMM y']);

        self::assertSame('Every day, until 31 December 2026', $description->describe($rule, 'en', new DateTimeZone('UTC')));
        /** @var array<string, array<string, array<string, IntlDateFormatter>>> $kept */
        $kept = $formatters->getValue($description);
        self::assertSame(['en' => ['d MMMM y' => ['UTC']]], array_map(static fn (array $patterns): array => array_map(array_keys(...), $patterns), $kept));

        // The count starts again: 64 formatters, then the 65th empties the cache once more.
        $zones = \array_slice(DateTimeZone::listIdentifiers(), 100, 64);

        foreach (\array_slice($zones, 0, 63) as $zone) {
            $description->describe($rule, 'en', new DateTimeZone($zone));
        }

        /** @var array<string, array<string, array<string, IntlDateFormatter>>> $kept */
        $kept = $formatters->getValue($description);
        self::assertCount(64, $kept['en']['d MMMM y']);
        $description->describe($rule, 'en', new DateTimeZone($zones[63]));
        /** @var array<string, array<string, array<string, IntlDateFormatter>>> $kept */
        $kept = $formatters->getValue($description);
        self::assertSame([$zones[63]], array_keys($kept['en']['d MMMM y']));
    }
}
