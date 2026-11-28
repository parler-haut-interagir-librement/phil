<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Unit\Application;

use Closure;
use DateTimeImmutable;
use Phil\Shifts\Roster\Place;
use Phil\Shifts\Roster\PlaceStatus;
use Phil\Shifts\Swap\SwapStatus;
use Phil\ShiftsBundle\Application\Exception\RequestRefused;
use Phil\ShiftsBundle\Application\Minors;
use Phil\ShiftsBundle\Application\Rosters;
use Phil\ShiftsBundle\Application\SwapBoard;
use Phil\ShiftsBundle\Domain\Exception\InvalidGuardianContact;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalGuardianRepository;
use Phil\ShiftsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Minors::class)]
#[CoversClass(Rosters::class)]
#[CoversClass(SwapBoard::class)]
#[CoversClass(DbalGuardianRepository::class)]
final class MinorsTest extends TestCase
{
    /** Seventeen on the day of the shifts, 2026-11-28; eighteen the day after. */
    private const string KID = '2008-11-29';

    /** Seventeen today, 2026-11-27, eighteen on the day of the shifts. */
    private const string ALMOST = '2008-11-28';

    public function testWithoutBirthDatesNobodyIsAMinor(): void
    {
        $stage = new Stage();
        $stage->rosters->plan(Stage::desk());

        self::assertFalse($stage->minors->isEnabled());
        self::assertFalse($stage->minors->isMinor('kid'));
        self::assertTrue($stage->minors->mayJoin('kid', Stage::desk()));
        self::assertSame(PlaceStatus::Confirmed, $stage->rosters->signUp('desk-am', 'kid', [])->statusOf('kid'));
    }

    public function testAVolunteerIsAMinorOnTheDayOfTheShift(): void
    {
        $stage = new Stage(birthDates: ['kid' => self::KID, 'almost' => self::ALMOST.'T23:30:00-05:00', 'adult' => '1990-01-01']);

        self::assertTrue($stage->minors->isEnabled());
        self::assertTrue($stage->minors->isMinor('kid'));
        self::assertTrue($stage->minors->isMinor('almost'));
        self::assertFalse($stage->minors->isMinor('adult'));
        self::assertFalse($stage->minors->isMinor('unknown'));
        self::assertTrue($stage->minors->isMinor('kid', Stage::at('23:59:59')));
        self::assertFalse($stage->minors->isMinor('kid', new DateTimeImmutable('2026-11-29T00:00:00Z')));
        // Only the date of birth counts: the hour and the offset given with it change nothing.
        self::assertFalse($stage->minors->isMinor('almost', Stage::at('00:00:00')));
        self::assertTrue($stage->minors->mayJoin('almost', Stage::desk()));
        self::assertFalse($stage->minors->mayJoin('kid', Stage::desk()));

        $sixteen = new Minors($stage->guardianRepository, $stage->clock, $stage->birthDates, 16);
        self::assertFalse($sixteen->isMinor('kid'));
        self::assertTrue($sixteen->isMinor('kid', new DateTimeImmutable('2024-11-28T12:00:00Z')));
        self::assertFalse($sixteen->isMinor('kid', new DateTimeImmutable('2024-11-29T00:00:00Z')));
    }

    public function testTheDayIsTheOneOfTheConfiguredTimeZone(): void
    {
        $stage = new Stage(birthDates: ['almost' => self::ALMOST], timeZone: 'America/Toronto');

        // 02:00 UTC on 2026-11-28 is still 2026-11-27 in Toronto: they are seventeen there.
        self::assertTrue($stage->minors->isMinor('almost', Stage::at('02:00:00')));
        self::assertFalse($stage->minors->isMinor('almost', Stage::at('05:00:00')));
        self::assertFalse(new Minors($stage->guardianRepository, $stage->clock, $stage->birthDates)->isMinor('almost', Stage::at('02:00:00')));
    }

    public function testAMinorJoinsOnceTheConsentOfAGuardianIsRecorded(): void
    {
        $stage = new Stage(birthDates: ['kid' => self::KID, 'almost' => self::ALMOST]);
        $stage->rosters->plan(Stage::desk());
        $stage->rosters->plan(Stage::cloakroom());

        $this->assertRequestRefused(RequestRefused::GUARDIAN_CONSENT, static fn (): mixed => $stage->rosters->signUp('desk-am', 'kid', []));
        $this->assertRequestRefused(RequestRefused::GUARDIAN_CONSENT, static fn (): mixed => $stage->rosters->expressInterest('cloakroom', 'kid', []));
        $this->assertRequestRefused(RequestRefused::GUARDIAN_CONSENT, static fn (): mixed => $stage->rosters->assign('desk-am', 'kid', 'grace'));
        self::assertSame(PlaceStatus::Confirmed, $stage->rosters->signUp('desk-am', 'almost', [])->statusOf('almost'));
        $this->assertRequestRefused(RequestRefused::NO_GUARDIAN, static fn (): mixed => $stage->minors->consent('kid', 'grace'));
        $this->assertRequestRefused(RequestRefused::NO_GUARDIAN, static fn (): mixed => $stage->minors->withdrawConsent('kid'));
        self::assertNull($stage->minors->contactOf('kid'));

        $contact = $stage->minors->record('kid', 'Marie Curie', '514-555-0100', null, 'grace');
        self::assertFalse($contact->hasConsent());
        self::assertEquals($contact, $stage->minors->contactOf('kid'));
        $this->assertRequestRefused(RequestRefused::GUARDIAN_CONSENT, static fn (): mixed => $stage->rosters->signUp('desk-am', 'kid', []));

        $stage->clock->modify('+5 minutes');
        $consented = $stage->minors->consent('kid', 'olga');
        self::assertEquals(new DateTimeImmutable('2026-11-27T10:05:00Z'), $consented->consentedAt);
        self::assertSame('olga', $consented->consentRecordedBy);
        self::assertEquals($consented, $stage->minors->contactOf('kid'));
        self::assertSame(PlaceStatus::Confirmed, $stage->rosters->signUp('desk-am', 'kid', [])->statusOf('kid'));

        // A correction keeps the consent already given.
        $corrected = $stage->minors->record('kid', 'Marie Curie', null, 'marie@example.org', 'grace');
        self::assertNull($corrected->phone);
        self::assertEquals(new DateTimeImmutable('2026-11-27T10:05:00Z'), $stage->minors->contactOf('kid')?->consentedAt);
        self::assertSame('olga', $stage->minors->contactOf('kid')?->consentRecordedBy);
        self::assertSame('marie@example.org', $stage->minors->contactOf('kid')?->email);

        $withdrawn = $stage->minors->withdrawConsent('kid');
        self::assertFalse($withdrawn->hasConsent());
        self::assertFalse($stage->minors->contactOf('kid')?->hasConsent());
        self::assertSame('Marie Curie', $stage->minors->contactOf('kid')?->name);
        // Withdrawing a consent takes nobody off a shift.
        self::assertSame(PlaceStatus::Confirmed, $stage->rosters->find('desk-am')?->statusOf('kid'));
        $this->assertRequestRefused(RequestRefused::GUARDIAN_CONSENT, static fn (): mixed => $stage->rosters->expressInterest('cloakroom', 'kid', []));

        $stage->minors->forget('kid');
        self::assertNull($stage->minors->contactOf('kid'));

        try {
            $stage->minors->record('kid', '', null, null, 'grace');
            self::fail('The contact is refused.');
        } catch (InvalidGuardianContact $invalidGuardianContact) {
            self::assertSame(InvalidGuardianContact::NAME, $invalidGuardianContact->reason);
        }

        self::assertNull($stage->minors->contactOf('kid'));
    }

    public function testAMinorWithoutConsentTakesNoSwapAndIsLeftOutOfTheAllocation(): void
    {
        $stage = new Stage(birthDates: ['kid' => self::KID]);
        $stage->rosters->plan(Stage::desk());
        $stage->rosters->plan(Stage::cloakroom(capacity: 3));
        $stage->rosters->signUp('desk-am', 'ada', []);

        $offer = $stage->swaps->offer('desk-am', 'ada', '');

        $this->assertRequestRefused(RequestRefused::GUARDIAN_CONSENT, static fn (): mixed => $stage->swaps->take($offer->id, 'kid', []));
        self::assertSame(SwapStatus::Open, $stage->swaps->find($offer->id)?->status);

        $stage->minors->record('kid', 'Marie Curie', '514-555-0100', null, 'grace');
        $stage->minors->consent('kid', 'grace');
        $stage->rosters->expressInterest('cloakroom', 'kid', []);
        $stage->rosters->expressInterest('cloakroom', 'bob', []);
        $stage->minors->withdrawConsent('kid');

        $allocated = $stage->rosters->allocate('cloakroom');
        self::assertSame(['bob'], array_map(static fn (Place $place): string => $place->volunteer, $allocated->confirmed()));
        self::assertSame(['kid'], array_map(static fn (Place $place): string => $place->volunteer, $allocated->interested()));

        $stage->minors->consent('kid', 'grace');
        self::assertSame(SwapStatus::Completed, $stage->swaps->take($offer->id, 'kid', [])->status);
        self::assertSame(PlaceStatus::Confirmed, $stage->rosters->find('desk-am')?->statusOf('kid'));
    }

    /**
     * @param Closure(): mixed $operation
     */
    private function assertRequestRefused(string $reason, Closure $operation): void
    {
        try {
            $operation();
            self::fail('The operation is refused.');
        } catch (RequestRefused $requestRefused) {
            self::assertSame($reason, $requestRefused->reason);
        }
    }
}
