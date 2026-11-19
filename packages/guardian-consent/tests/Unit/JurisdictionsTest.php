<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Tests\Unit;

use DateTimeZone;
use Phil\GuardianConsent\Exception\InvalidConfiguration;
use Phil\GuardianConsent\Exception\UnknownJurisdiction;
use Phil\GuardianConsent\Jurisdiction;
use Phil\GuardianConsent\Jurisdictions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Jurisdictions::class)]
#[CoversClass(UnknownJurisdiction::class)]
final class JurisdictionsTest extends TestCase
{
    public function testQuebecIsTheDefault(): void
    {
        $jurisdictions = new Jurisdictions();

        self::assertSame('CA-QC', $jurisdictions->default->code);
        self::assertSame(14, $jurisdictions->get()->consentAge);
        self::assertSame(18, $jurisdictions->get()->majorityAge);
        self::assertSame('America/Toronto', $jurisdictions->get()->timeZone->getName());
        self::assertSame($jurisdictions->default, $jurisdictions->get('CA-QC'));
        self::assertSame(['CA-QC'], $jurisdictions->codes());
    }

    public function testJurisdictionsByCode(): void
    {
        $france = new Jurisdiction('FR', 15, 18, new DateTimeZone('Europe/Paris'));
        $germany = new Jurisdiction('DE', 16, 18, new DateTimeZone('Europe/Berlin'));
        $jurisdictions = new Jurisdictions(Jurisdiction::quebec(), $france, $germany);

        self::assertSame($france, $jurisdictions->get('FR'));
        self::assertSame($germany, $jurisdictions->get('DE'));
        self::assertSame(['CA-QC', 'FR', 'DE'], $jurisdictions->codes());
    }

    public function testAnUnknownCode(): void
    {
        $this->expectException(UnknownJurisdiction::class);
        $this->expectExceptionMessageIsOrContains('No jurisdiction is configured under the code "FR".');

        new Jurisdictions()->get('FR');
    }

    public function testACodeGivenTwice(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('The jurisdiction "CA-QC" is given twice.');

        new Jurisdictions(Jurisdiction::quebec(), Jurisdiction::quebec());
    }

    public function testAtMost300Jurisdictions(): void
    {
        $others = [];

        foreach (range(0, 298) as $index) {
            $others[] = new Jurisdiction(\sprintf('FR-%03d', $index), 15, 18, new DateTimeZone('Europe/Paris'));
        }

        self::assertCount(300, new Jurisdictions(Jurisdiction::quebec(), ...$others)->codes());

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('At most 300 jurisdictions are served.');

        new Jurisdictions(Jurisdiction::quebec(), ...[...$others, new Jurisdiction('FR-999', 15, 18, new DateTimeZone('Europe/Paris'))]);
    }
}
