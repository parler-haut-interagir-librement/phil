<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Tests\Unit;

use DateTimeZone;
use Phil\GuardianConsent\AgeAssessment;
use Phil\GuardianConsent\AgeBracket;
use Phil\GuardianConsent\CalendarDate;
use Phil\GuardianConsent\Exception\InvalidConfiguration;
use Phil\GuardianConsent\Exception\InvalidConsent;
use Phil\GuardianConsent\Jurisdiction;
use Phil\GuardianConsent\LeapDayRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Jurisdiction::class)]
#[CoversClass(AgeAssessment::class)]
#[CoversClass(AgeBracket::class)]
#[CoversClass(InvalidConfiguration::class)]
final class JurisdictionTest extends TestCase
{
    public function testQuebecIsFourteenForTheConsentAndEighteenForMajority(): void
    {
        $quebec = Jurisdiction::quebec();

        self::assertSame('CA-QC', $quebec->code);
        self::assertSame(Jurisdiction::QUEBEC, $quebec->code);
        self::assertSame(14, $quebec->consentAge);
        self::assertSame(18, $quebec->majorityAge);
        self::assertSame('America/Toronto', $quebec->timeZone->getName());
        self::assertSame(LeapDayRule::FirstOfMarch, $quebec->leapDayRule);
    }

    public function testAConfiguredJurisdiction(): void
    {
        $germany = new Jurisdiction('DE', 16, 18, new DateTimeZone('Europe/Berlin'), LeapDayRule::TwentyEighthOfFebruary);

        self::assertSame('DE', $germany->code);
        self::assertSame(16, $germany->consentAge);
        self::assertSame(LeapDayRule::TwentyEighthOfFebruary, $germany->leapDayRule);
        self::assertSame('2028-02-29', $germany->consentAgeOn(CalendarDate::fromString('2012-02-29'))->toString());
        self::assertSame('2029-02-28', $germany->consentAgeOn(CalendarDate::fromString('2013-02-28'))->toString());
        self::assertSame('2027-02-28', new Jurisdiction('FR', 15, 18, new DateTimeZone('Europe/Paris'), LeapDayRule::TwentyEighthOfFebruary)->consentAgeOn(CalendarDate::fromString('2012-02-29'))->toString());
        self::assertSame('2027-03-01', new Jurisdiction('FR', 15, 18, new DateTimeZone('Europe/Paris'))->consentAgeOn(CalendarDate::fromString('2012-02-29'))->toString());
        self::assertSame(21, new Jurisdiction('US-MS', 21, 21, new DateTimeZone('America/Chicago'))->consentAge);
        self::assertSame(1, new Jurisdiction('ES-CT', 1, 1, new DateTimeZone('Europe/Madrid'))->majorityAge);
        self::assertSame('ABC', substr(new Jurisdiction('FR-ABC', 15, 18, new DateTimeZone('Europe/Paris'))->code, 3));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCodes(): iterable
    {
        yield 'lowercase' => ['ca-qc'];
        yield 'three letters' => ['CAN'];
        yield 'one letter' => ['C'];
        yield 'empty subdivision' => ['CA-'];
        yield 'long subdivision' => ['CA-QCQC'];
        yield 'trailing newline' => ["CA\n"];
        yield 'leading text' => ['xCA'];
    }

    #[DataProvider('invalidCodes')]
    public function testACodeThatIsNotIso3166IsRefused(string $code): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('A jurisdiction is named by an ISO 3166 code, such as "FR" or "CA-QC".');

        new Jurisdiction($code, 14, 18, new DateTimeZone('UTC'));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function invalidAges(): iterable
    {
        yield 'consent at 0' => [0, 18];
        yield 'consent after majority' => [19, 18];
        yield 'majority past 21' => [14, 22];
    }

    #[DataProvider('invalidAges')]
    public function testAnAgeOutOfBoundsIsRefused(int $consent, int $majority): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('The age of consent is from 1 to the age of majority, which is at most 21.');

        new Jurisdiction('FR', $consent, $majority, new DateTimeZone('UTC'));
    }

    public function testTheBracketChangesOnTheBirthday(): void
    {
        $quebec = Jurisdiction::quebec();
        $birth = CalendarDate::fromString('2012-11-14');

        $before = $quebec->assess($birth, new MockClock('2026-11-13 12:00:00', 'America/Toronto'));
        self::assertSame(13, $before->age);
        self::assertSame(AgeBracket::BelowConsentAge, $before->bracket);
        self::assertTrue($before->needsGuardian());
        self::assertSame('CA-QC', $before->jurisdiction);
        self::assertSame('2026-11-13', $before->today->toString());
        self::assertSame('2026-11-14', $before->consentAgeOn->toString());
        self::assertSame('2030-11-14', $before->majorityOn->toString());

        $on = $quebec->assess($birth, new MockClock('2026-11-14 00:00:00', 'America/Toronto'));
        self::assertSame(14, $on->age);
        self::assertSame(AgeBracket::MinorAtConsentAge, $on->bracket);
        self::assertFalse($on->needsGuardian());

        self::assertSame(AgeBracket::MinorAtConsentAge, $quebec->assess($birth, new MockClock('2030-11-13 23:59:59', 'America/Toronto'))->bracket);
        self::assertSame(AgeBracket::Adult, $quebec->assess($birth, new MockClock('2030-11-14 00:00:00', 'America/Toronto'))->bracket);
    }

    public function testTheBirthdayBeginsInTheJurisdictionsTimeZone(): void
    {
        $birth = CalendarDate::fromString('2012-11-14');

        // 03:30 UTC on the 14th is still the 13th in Quebec, already the 14th in Paris.
        $clock = new MockClock('2026-11-14 03:30:00', 'UTC');

        self::assertSame(13, Jurisdiction::quebec()->assess($birth, $clock)->age);
        self::assertSame(14, new Jurisdiction('FR', 15, 18, new DateTimeZone('Europe/Paris'))->assess($birth, $clock)->age);
        self::assertSame('2026-11-13', Jurisdiction::quebec()->today($clock)->toString());
    }

    public function testABirthdayOn29FebruaryFollowsTheRuleOfTheJurisdiction(): void
    {
        $birth = CalendarDate::fromString('2012-02-29');
        $clock = new MockClock('2026-02-28 12:00:00', 'America/Toronto');

        self::assertSame(AgeBracket::BelowConsentAge, Jurisdiction::quebec()->assess($birth, $clock)->bracket);
        self::assertSame('2026-03-01', Jurisdiction::quebec()->assess($birth, $clock)->consentAgeOn->toString());

        $february = new Jurisdiction('CA-ON', 14, 18, new DateTimeZone('America/Toronto'), LeapDayRule::TwentyEighthOfFebruary);
        self::assertSame(AgeBracket::MinorAtConsentAge, $february->assess($birth, $clock)->bracket);
        self::assertSame('2026-02-28', $february->assess($birth, $clock)->consentAgeOn->toString());
    }

    public function testADateOfBirthAfterTodayIsRefused(): void
    {
        $this->expectException(InvalidConsent::class);
        $this->expectExceptionMessageIsOrContains('A date of birth is not after today.');

        Jurisdiction::quebec()->assess(CalendarDate::fromString('2026-11-15'), new MockClock('2026-11-14 12:00:00', 'America/Toronto'));
    }

    public function testADateOfBirthToday(): void
    {
        $assessment = Jurisdiction::quebec()->assess(CalendarDate::fromString('2026-11-14'), new MockClock('2026-11-14 12:00:00', 'America/Toronto'));

        self::assertSame(0, $assessment->age);
        self::assertSame(AgeBracket::BelowConsentAge, $assessment->bracket);
    }

    public function testADateOfBirthAtMost150YearsAgo(): void
    {
        $clock = new MockClock('2026-11-14 12:00:00', 'America/Toronto');

        self::assertSame(150, Jurisdiction::quebec()->assess(CalendarDate::fromString('1876-11-14'), $clock)->age);

        $this->expectException(InvalidConsent::class);
        $this->expectExceptionMessageIsOrContains('A date of birth is at most 150 years before today.');

        Jurisdiction::quebec()->assess(CalendarDate::fromString('1875-11-14'), $clock);
    }
}
