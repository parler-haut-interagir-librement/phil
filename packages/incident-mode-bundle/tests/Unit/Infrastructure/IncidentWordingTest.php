<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\Preset;
use Phil\IncidentMode\Presets;
use Phil\IncidentMode\Restriction;
use Phil\IncidentModeBundle\Application\Suspension;
use Phil\IncidentModeBundle\Infrastructure\Translation\IncidentWording;
use Phil\IncidentModeBundle\Tests\Unit\Fixture\Words;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IncidentWording::class)]
final class IncidentWordingTest extends TestCase
{
    public function testASuspensionIsSaidInTheLanguageAndTheTimeZoneOfTheVisitor(): void
    {
        $wording = new IncidentWording(Words::translator(), 'America/Toronto');
        $suspension = new Suspension(Restriction::SIGNUPS, new DateTimeImmutable('2026-11-17 17:00 UTC'));

        self::assertSame('Sign-ups are suspended until November 17, 2026 at 12:00 PM (Eastern Standard Time).', $this->plain($wording->sentence($suspension)));
        self::assertSame('Les inscriptions sont suspendues jusqu’au 17 novembre 2026 à 12:00 (heure normale de l’Est nord-américain).', $this->plain($wording->sentence($suspension, 'fr')));
        self::assertSame('America/Toronto', $wording->timeZone());
    }

    public function testTheLocaleOfTheTranslatorIsTheDefault(): void
    {
        $wording = new IncidentWording(Words::translator('fr'), 'Europe/Paris');

        self::assertSame('17 novembre 2026 à 18:00 (heure normale d’Europe centrale)', $this->plain($wording->moment(new DateTimeImmutable('2026-11-17 17:00 UTC'))));
    }

    public function testWithoutATimeZoneTheOneOfPhpIsUsed(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo');

        try {
            $wording = new IncidentWording(Words::translator());
            self::assertSame('Asia/Tokyo', $wording->timeZone());
            self::assertSame('November 18, 2026 at 2:00 AM (Japan Standard Time)', $this->plain($wording->moment(new DateTimeImmutable('2026-11-17 17:00 UTC'))));
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function testEveryBuiltInRestrictionHasItsSentenceAndOthersKeepTheirName(): void
    {
        $wording = new IncidentWording(Words::translator(), 'UTC');
        $until = new DateTimeImmutable('2026-11-17 17:00 UTC');

        foreach (IncidentWording::RESTRICTIONS as $restriction) {
            self::assertStringNotContainsString('suspended.', $wording->sentence(new Suspension($restriction, $until)));
            self::assertStringNotContainsString('restriction.', $wording->restriction($restriction));
        }

        self::assertSame('“new-listings” is suspended until November 17, 2026 at 5:00 PM (Coordinated Universal Time).', $this->plain($wording->sentence(new Suspension('new-listings', $until))));
        self::assertSame('The site is read only until November 17, 2026 at 5:00 PM (Coordinated Universal Time): you can read it, but nothing can be added or changed.', $this->plain($wording->sentence(new Suspension(Restriction::READ_ONLY, $until))));
        self::assertSame('new-listings', $wording->restriction('new-listings'));
        self::assertSame('Sign-ups', $wording->restriction(Restriction::SIGNUPS));
        self::assertSame('Inscriptions', $wording->restriction(Restriction::SIGNUPS, 'fr'));
    }

    public function testABuiltInPresetHasItsLabelInEachLanguageAndOthersKeepTheirOwn(): void
    {
        $wording = new IncidentWording(Words::translator(), 'UTC');
        $listings = Preset::of('suspend-listings', 'Suspend new listings', ['new-listings'], Duration::ofHours(4), Duration::ofDays(2));

        foreach (Presets::defaults()->all() as $preset) {
            self::assertStringNotContainsString('preset.', $wording->preset($preset));
            self::assertStringNotContainsString('preset.', $wording->preset($preset, 'fr'));
        }

        self::assertSame('Suspend sign-ups', $wording->preset(Presets::SUSPEND_SIGNUPS));
        self::assertSame('Suspendre les inscriptions', $wording->preset(Presets::defaults()->get(Presets::SUSPEND_SIGNUPS), 'fr'));
        self::assertSame('Suspend new listings', $wording->preset($listings, 'fr'));
        self::assertSame('suspend-listings', $wording->preset('suspend-listings'));
    }

    public function testADurationIsSaidInItsLargestWholeUnit(): void
    {
        $wording = new IncidentWording(Words::translator(), 'UTC');

        self::assertSame('2 days', $wording->duration(Duration::ofDays(2)));
        self::assertSame('1 hour', $wording->duration(Duration::ofHours(1)));
        self::assertSame('90 minutes', $wording->duration(Duration::ofMinutes(90)));
        self::assertSame('1 jour', $wording->duration(Duration::ofDays(1), 'fr'));
        self::assertSame('36 heures', $wording->duration(Duration::ofHours(36), 'fr'));
    }

    public function testEveryKeyIsTranslatedInBothLanguages(): void
    {
        $english = require \dirname(__DIR__, 3).'/translations/phil_incident_mode.en.php';
        $french = require \dirname(__DIR__, 3).'/translations/phil_incident_mode.fr.php';

        self::assertIsArray($english);
        self::assertIsArray($french);
        self::assertSame(array_keys($english), array_keys($french));
    }

    private function plain(string $text): string
    {
        return str_replace(["\u{202F}", "\u{00A0}"], ' ', $text);
    }
}
