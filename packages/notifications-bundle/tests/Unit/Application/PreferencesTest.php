<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use Phil\NotificationsBundle\Application\Preferences\ConfiguredPreferences;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Application\Preferences\QuietHours;
use Phil\NotificationsBundle\Application\Preferences\RecipientSettings;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(DigestFrequency::class)]
#[CoversClass(QuietHours::class)]
#[CoversClass(RecipientSettings::class)]
#[CoversClass(ConfiguredPreferences::class)]
final class PreferencesTest extends TestCase
{
    public function testEachFrequencyHasItsWindow(): void
    {
        self::assertNull(DigestFrequency::Never->window());
        self::assertSame('-1 day', DigestFrequency::Daily->window());
        self::assertSame('-7 days', DigestFrequency::Weekly->window());
    }

    #[TestWith(['21:59', false])]
    #[TestWith(['22:00', true])]
    #[TestWith(['23:59', true])]
    #[TestWith(['00:00', true])]
    #[TestWith(['06:59', true])]
    #[TestWith(['07:00', false])]
    #[TestWith(['12:00', false])]
    public function testQuietHoursGoPastMidnight(string $time, bool $quiet): void
    {
        self::assertSame($quiet, QuietHours::of('22:00', '07:00', 'America/Toronto')->contains(new DateTimeImmutable('2026-11-08 '.$time, new DateTimeZone('America/Toronto'))));
    }

    #[TestWith(['12:59', false])]
    #[TestWith(['13:00', true])]
    #[TestWith(['13:59', true])]
    #[TestWith(['14:00', false])]
    public function testQuietHoursWithinADay(string $time, bool $quiet): void
    {
        self::assertSame($quiet, QuietHours::of('13:00', '14:00')->contains(new DateTimeImmutable('2026-11-08 '.$time.' UTC')));
    }

    public function testQuietHoursAreReadInTheirTimeZone(): void
    {
        $hours = QuietHours::of('22:00', '07:00', 'America/Toronto');

        self::assertSame('America/Toronto', $hours->timezone->getName());
        self::assertSame('22:00', $hours->start);
        self::assertSame('07:00', $hours->end);
        self::assertTrue($hours->contains(new DateTimeImmutable('2026-11-08 04:00 UTC')));
        self::assertFalse($hours->contains(new DateTimeImmutable('2026-11-08 13:00 UTC')));
        self::assertSame('UTC', QuietHours::of('01:00', '02:00')->timezone->getName());
    }

    public function testTheSameStartAndEndMakeNoQuietHours(): void
    {
        $hours = QuietHours::of('08:00', '08:00');

        self::assertFalse($hours->contains(new DateTimeImmutable('2026-11-08 08:00 UTC')));
        self::assertFalse($hours->contains(new DateTimeImmutable('2026-11-08 20:00 UTC')));
    }

    #[TestWith(['24:00', '07:00'])]
    #[TestWith(['22:00', '7:00'])]
    #[TestWith(['22:60', '07:00'])]
    #[TestWith(['22:00', "07:00\n"])]
    public function testQuietHoursAreTimes(string $start, string $end): void
    {
        $this->expectException(InvalidNotification::class);
        $this->expectExceptionMessageIsOrContains('The quiet hours are times written "HH:MM", not');

        QuietHours::of($start, $end);
    }

    public function testQuietHoursHaveAKnownTimeZone(): void
    {
        $this->expectExceptionMessageIsOrContains('The time zone of the quiet hours, "Mars/Olympus", is not one of the time zone database.');

        QuietHours::of('22:00', '07:00', 'Mars/Olympus');
    }

    public function testSettingsHaveDefaults(): void
    {
        $settings = new RecipientSettings();

        self::assertSame(DigestFrequency::Never, $settings->digest);
        self::assertNull($settings->quietHours);
        self::assertSame([], $settings->optOuts);
        self::assertNull($settings->locale);
        self::assertTrue($settings->allows('a', 'email'));
    }

    public function testAChannelIsTurnedOffForATypeOrForAll(): void
    {
        $settings = new RecipientSettings(DigestFrequency::Daily, null, ['comment.replied:email', '*:web_push', 'comment.replied:email'], 'fr_CA');

        self::assertSame(['*:web_push', 'comment.replied:email'], $settings->optOuts);
        self::assertSame('fr_CA', $settings->locale);
        self::assertFalse($settings->allows('comment.replied', 'email'));
        self::assertTrue($settings->allows('event.created', 'email'));
        self::assertFalse($settings->allows('event.created', 'web_push'));
        self::assertTrue($settings->allows('comment.replied', 'site'));
    }

    #[TestWith(['Bad:email', 'The opted-out type (length 3)'])]
    #[TestWith(['comment', '"" is not the name of a channel'])]
    #[TestWith(['comment:Email', '"Email" is not the name of a channel'])]
    #[TestWith(['comment:email:x', '"email:x" is not the name of a channel'])]
    public function testAnOptOutIsChecked(string $optOut, string $message): void
    {
        $this->expectExceptionMessageIsOrContains($message);

        new RecipientSettings(optOuts: [$optOut]);
    }

    #[TestWith(['fr'])]
    #[TestWith(['fr_CA'])]
    #[TestWith(['zh-Hant-TW'])]
    public function testALocaleIsTaken(string $locale): void
    {
        self::assertSame($locale, new RecipientSettings(locale: $locale)->locale);
    }

    #[TestWith(['F'])]
    #[TestWith(['french'])]
    #[TestWith(["fr\n"])]
    #[TestWith(['fr_'])]
    public function testALocaleIsChecked(string $locale): void
    {
        $this->expectExceptionMessageIsOrContains('"'.$locale.'" is not a locale.');

        new RecipientSettings(locale: $locale);
    }

    public function testConfiguredPreferencesAreTheSameForEveryone(): void
    {
        $defaults = new RecipientSettings(DigestFrequency::Weekly);

        self::assertSame($defaults, new ConfiguredPreferences($defaults)->settingsFor('42'));
        self::assertSame(DigestFrequency::Never, new ConfiguredPreferences()->settingsFor('42')->digest);
    }
}
