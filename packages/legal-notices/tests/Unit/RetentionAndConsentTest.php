<?php

declare(strict_types=1);

namespace Phil\LegalNotices\Tests\Unit;

use Phil\LegalNotices\ConsentCategory;
use Phil\LegalNotices\DurationWords;
use Phil\LegalNotices\Exception\InvalidLegalNotice;
use Phil\LegalNotices\RetentionDuration;
use Phil\LegalNotices\RetentionKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetentionDuration::class)]
#[CoversClass(DurationWords::class)]
#[CoversClass(ConsentCategory::class)]
final class RetentionAndConsentTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function durations(): iterable
    {
        yield 'days' => ['P90D', '90 days', '90 jours'];
        yield 'one day' => ['P1D', '1 day', '1 jour'];
        yield 'one year' => ['P1Y', '1 year', '1 an'];
        yield 'years and months' => ['P2Y6M', '2 years and 6 months', '2 ans et 6 mois'];
        yield 'one month' => ['P1M', '1 month', '1 mois'];
        yield 'every part' => ['P1Y2M3W4DT5H6M7S', '1 year, 2 months, 3 weeks, 4 days, 5 hours, 6 minutes and 7 seconds', '1 an, 2 mois, 3 semaines, 4 jours, 5 heures, 6 minutes et 7 secondes'];
        yield 'each one' => ['P1WT1H1M1S', '1 week, 1 hour, 1 minute and 1 second', '1 semaine, 1 heure, 1 minute et 1 seconde'];
        yield 'zeros left out' => ['P0Y13M0D', '13 months', '13 mois'];
        yield 'time only' => ['PT36H', '36 hours', '36 heures'];
        yield 'four digits' => ['P9999D', '9999 days', '9999 jours'];
    }

    #[DataProvider('durations')]
    public function testADurationInWords(string $duration, string $english, string $french): void
    {
        self::assertSame($english, DurationWords::duration($duration, 'en'));
        self::assertSame($english, DurationWords::duration($duration, 'de'));
        self::assertSame($french, DurationWords::duration($duration, 'fr_CA'));
    }

    public function testTheParts(): void
    {
        self::assertSame([1 => 13], DurationWords::parts('P0Y13M'));
        self::assertSame([0 => 1, 6 => 2], DurationWords::parts('P1YT2S'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badDurations(): iterable
    {
        yield 'empty' => ['', 'duration'];
        yield 'P alone' => ['P', 'duration'];
        yield 'T alone' => ['P1DT', 'duration'];
        yield 'five digits' => ['P10000D', 'duration'];
        yield 'lower case' => ['p1d', 'duration'];
        yield 'a trailing line' => ["P1D\n", 'duration'];
        yield 'a prefix' => ['XP1D', 'duration'];
        yield 'decimals' => ['P1.5D', 'duration'];
        yield 'nothing' => ['P0D', 'zero_duration'];
        yield 'zeros' => ['P0Y0M0W0DT0H0M0S', 'zero_duration'];
        yield 'nothing in time' => ['PT0S', 'zero_duration'];
    }

    #[DataProvider('badDurations')]
    public function testABadDurationIsRefused(string $duration, string $reason): void
    {
        try {
            DurationWords::parts($duration);
            self::fail('The duration was accepted.');
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            self::assertSame($reason, $invalidLegalNotice->reason);
            self::assertSame('duration' === $reason ? \sprintf('"%s" is not an ISO 8601 duration such as "P90D" or "P2Y".', $duration) : \sprintf('"%s" is a duration of nothing.', $duration), $invalidLegalNotice->getMessage());
        }
    }

    public function testALongDurationIsCutInTheMessage(): void
    {
        $this->expectExceptionMessageIsOrContains('"P'.str_repeat('1', 79).'" is not an ISO 8601 duration');

        DurationWords::parts('P'.str_repeat('1', 90));
    }

    public function testRetentionPeriodsInWords(): void
    {
        $fixed = new RetentionDuration('R-001', 'Account data', RetentionKind::Fixed, 'P2Y', labels: ['fr' => 'Données du compte']);
        $event = new RetentionDuration('R.002', 'Orders', RetentionKind::UntilEvent, note: 'the life of the account');
        $indefinite = new RetentionDuration('r_3', 'Invoices', RetentionKind::Indefinite, note: 'legal archive');
        $open = new RetentionDuration('R4', 'Logs', RetentionKind::NotYetFixed);

        self::assertSame(['2 years', '2 ans'], [DurationWords::describe($fixed, 'en'), DurationWords::describe($fixed, 'fr')]);
        self::assertSame('the life of the account', DurationWords::describe($event, 'fr'));
        self::assertSame(['kept with no set end', 'conservées sans échéance fixée'], [DurationWords::describe($indefinite, 'en'), DurationWords::describe($indefinite, 'fr')]);
        self::assertSame(['not set yet', 'pas encore fixée'], [DurationWords::describe($open, 'en_GB'), DurationWords::describe($open, 'fr')]);
        self::assertSame(['Données du compte', 'Account data', 'Données du compte'], [$fixed->label('fr_CA'), $fixed->label('en'), $fixed->label('fr')]);
        self::assertSame(['legal archive', null, 'P2Y', null], [$indefinite->note, $open->note, $fixed->duration, $open->duration]);
        self::assertSame(str_repeat('a', 300), new RetentionDuration('a', str_repeat('a', 300), RetentionKind::Indefinite)->label);
        self::assertSame('a'.str_repeat('b', 63), new RetentionDuration('a'.str_repeat('b', 63), 'x', RetentionKind::Indefinite)->id);
    }

    /**
     * @return iterable<string, array{string, string, RetentionKind, ?string, ?string, array<array-key, mixed>, string, string}>
     */
    public static function badRetentions(): iterable
    {
        yield 'a bad identifier' => ['-R', 'x', RetentionKind::Indefinite, null, null, [], 'retention_id', '"-R" is not a retention identifier: letters, digits, ".", "_" and "-", 64 characters at most.'];
        yield 'an identifier too long' => ['a'.str_repeat('b', 64), 'x', RetentionKind::Indefinite, null, null, [], 'retention_id', '"a'.str_repeat('b', 64).'" is not a retention identifier: letters, digits, ".", "_" and "-", 64 characters at most.'];
        yield 'an identifier with a line' => ["R\n", 'x', RetentionKind::Indefinite, null, null, [], 'retention_id', "\"R\n\" is not a retention identifier: letters, digits, \".\", \"_\" and \"-\", 64 characters at most."];
        yield 'no label' => ['R', ' ', RetentionKind::Indefinite, null, null, [], 'retention_label', 'Retention R says what it keeps in 1 to 300 bytes.'];
        yield 'a label too long' => ['R', str_repeat('a', 301), RetentionKind::Indefinite, null, null, [], 'retention_label', 'Retention R says what it keeps in 1 to 300 bytes.'];
        yield 'fixed without a duration' => ['R', 'x', RetentionKind::Fixed, null, null, [], 'duration', '"" is not an ISO 8601 duration such as "P90D" or "P2Y".'];
        yield 'fixed with a bad duration' => ['R', 'x', RetentionKind::Fixed, '90 days', null, [], 'duration', '"90 days" is not an ISO 8601 duration such as "P90D" or "P2Y".'];
        yield 'a duration that is not fixed' => ['R', 'x', RetentionKind::Indefinite, 'P1D', null, [], 'retention_duration', 'Retention R is not fixed: it has no duration.'];
        yield 'an event unnamed' => ['R', 'x', RetentionKind::UntilEvent, null, null, [], 'retention_event', 'Retention R runs until an event, which it names.'];
        yield 'a bad label' => ['R', 'x', RetentionKind::Indefinite, null, null, ['fr' => ''], 'empty_text', 'The retention label in "fr" is empty.'];
        yield 'a label of a locale too long' => ['R', 'x', RetentionKind::Indefinite, null, null, ['fr' => str_repeat('a', 301)], 'invalid_text', 'The retention label in "fr" is UTF-8 text of 300 bytes at most.'];
    }

    /**
     * @param array<array-key, mixed> $labels
     */
    #[DataProvider('badRetentions')]
    public function testABadRetentionIsRefused(string $id, string $label, RetentionKind $kind, ?string $duration, ?string $note, array $labels, string $reason, string $message): void
    {
        try {
            new RetentionDuration($id, $label, $kind, $duration, $note, $labels);
            self::fail('The retention was accepted.');
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            self::assertSame([$reason, $message], [$invalidLegalNotice->reason, $invalidLegalNotice->getMessage()]);
        }
    }

    public function testALongRetentionIdentifierIsCutInTheMessage(): void
    {
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('-', 80).'" is not a retention identifier');

        new RetentionDuration(str_repeat('-', 90), 'x', RetentionKind::Indefinite);
    }

    public function testAConsentCategory(): void
    {
        $category = new ConsentCategory('analytics', false, ['en' => 'Audience measurement', 'fr' => "Mesure d'audience"], ['fr' => 'Pages vues, sans profil.'], ['_pk_id.*', 'session']);

        self::assertSame(['analytics', false, ['_pk_id.*', 'session']], [$category->key, $category->necessary, $category->cookies]);
        self::assertSame("Mesure d'audience", $category->label('fr_CA', 'en'));
        self::assertSame('Audience measurement', $category->label('de', 'en'));
        self::assertSame('Pages vues, sans profil.', $category->description('en', 'en'));
        self::assertSame(['fr' => 'Pages vues, sans profil.'], $category->descriptions);

        $necessary = new ConsentCategory('a'.str_repeat('b', 63), true, ['en' => str_repeat('a', 200)], ['en' => str_repeat('a', 2000)], array_fill(0, 100, str_repeat('c', 128)));
        self::assertTrue($necessary->necessary);
        self::assertNull(new ConsentCategory('necessary', true, ['en' => 'Necessary'])->description('en', 'en'));
        self::assertCount(100, $necessary->cookies);
        self::assertSame(['en' => str_repeat('a', 200)], $necessary->labels);
    }

    /**
     * @return iterable<string, array{string, array<array-key, mixed>, array<array-key, mixed>, array<array-key, mixed>, string, string}>
     */
    public static function badCategories(): iterable
    {
        yield 'a bad key' => ['Analytics', ['en' => 'a'], [], [], 'consent_key', '"Analytics" is not a consent category key: a lowercase letter, then lowercase letters, digits and "_", 64 characters at most.'];
        yield 'a key too long' => ['a'.str_repeat('b', 64), ['en' => 'a'], [], [], 'consent_key', '"a'.str_repeat('b', 64).'" is not a consent category key: a lowercase letter, then lowercase letters, digits and "_", 64 characters at most.'];
        yield 'a key with a line' => ["a\n", ['en' => 'a'], [], [], 'consent_key', "\"a\n\" is not a consent category key: a lowercase letter, then lowercase letters, digits and \"_\", 64 characters at most."];
        yield 'a key with a prefix' => ['_a', ['en' => 'a'], [], [], 'consent_key', '"_a" is not a consent category key: a lowercase letter, then lowercase letters, digits and "_", 64 characters at most.'];
        yield 'no name' => ['a', [], [], [], 'consent_label', 'The consent category a has a name in one locale at least.'];
        yield 'a name too long' => ['a', ['en' => str_repeat('a', 201)], [], [], 'invalid_text', 'The consent category name in "en" is UTF-8 text of 200 bytes at most.'];
        yield 'a description too long' => ['a', ['en' => 'a'], ['en' => str_repeat('a', 2001)], [], 'invalid_text', 'The consent category description in "en" is UTF-8 text of 2000 bytes at most.'];
        yield 'too many cookies' => ['a', ['en' => 'a'], [], array_fill(0, 101, 'c'), 'too_many_cookies', 'The consent category a names 100 cookies at most.'];
        yield 'a cookie that is no text' => ['a', ['en' => 'a'], [], [1], 'cookie_name', 'The consent category a names a cookie that is not a cookie name.'];
        yield 'a cookie with a space' => ['a', ['en' => 'a'], [], ['a b'], 'cookie_name', 'The consent category a names a cookie that is not a cookie name.'];
        yield 'a cookie too long' => ['a', ['en' => 'a'], [], [str_repeat('c', 129)], 'cookie_name', 'The consent category a names a cookie that is not a cookie name.'];
        yield 'a cookie with a line' => ['a', ['en' => 'a'], [], ["c\n"], 'cookie_name', 'The consent category a names a cookie that is not a cookie name.'];
        yield 'an empty cookie' => ['a', ['en' => 'a'], [], [''], 'cookie_name', 'The consent category a names a cookie that is not a cookie name.'];
    }

    /**
     * @param array<array-key, mixed> $labels
     * @param array<array-key, mixed> $descriptions
     * @param array<array-key, mixed> $cookies
     */
    #[DataProvider('badCategories')]
    public function testABadCategoryIsRefused(string $key, array $labels, array $descriptions, array $cookies, string $reason, string $message): void
    {
        try {
            new ConsentCategory($key, false, $labels, $descriptions, $cookies);
            self::fail('The category was accepted.');
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            self::assertSame([$reason, $message], [$invalidLegalNotice->reason, $invalidLegalNotice->getMessage()]);
        }
    }

    public function testALongKeyIsCutInTheMessage(): void
    {
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('A', 80).'" is not a consent category key');

        new ConsentCategory(str_repeat('A', 90), false, ['en' => 'a']);
    }
}
