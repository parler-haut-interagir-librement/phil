<?php

declare(strict_types=1);

namespace Phil\Shifts\Tests\Unit\Swap;

use Closure;
use DateTimeImmutable;
use Phil\Shifts\Event\PlaceTransferred;
use Phil\Shifts\Event\SwapOffered;
use Phil\Shifts\Event\SwapTaken;
use Phil\Shifts\Exception\InvalidShift;
use Phil\Shifts\Exception\ShiftRefused;
use Phil\Shifts\Roster\Place;
use Phil\Shifts\Roster\PlaceStatus;
use Phil\Shifts\Roster\Roster;
use Phil\Shifts\Swap\SwapOffer;
use Phil\Shifts\Swap\SwapStatus;
use Phil\Shifts\Tests\Fixtures\Shifts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SwapOffer::class)]
#[CoversClass(SwapStatus::class)]
#[CoversClass(SwapOffered::class)]
#[CoversClass(SwapTaken::class)]
final class SwapOfferTest extends TestCase
{
    public function testAPlacePassesAtOnceWithoutApproval(): void
    {
        $roster = $this->roster(false);
        $offer = SwapOffer::offer('swap-1', $roster, 'ada', 'Sick, sorry', Shifts::at('07:00:00'));

        self::assertSame(SwapStatus::Open, $offer->status);
        self::assertSame('desk-am', $offer->shiftId);
        self::assertSame('ada', $offer->offeredBy);
        self::assertSame('Sick, sorry', $offer->note);
        self::assertNull($offer->takenBy);
        self::assertNull($offer->decidedBy);
        self::assertNull($offer->decidedAt);
        self::assertSame('2026-11-28 07:00:00 UTC', $offer->createdAt->format('Y-m-d H:i:s T'));
        self::assertFalse($offer->isStale($roster));

        $taken = $offer->take($roster, 'cy', [], Shifts::at('07:30:00'));
        self::assertSame(SwapStatus::Completed, $taken->status);
        self::assertSame('cy', $taken->takenBy);
        self::assertNull($taken->decidedBy);
        self::assertSame('2026-11-28 07:30:00', $taken->decidedAt?->format('Y-m-d H:i:s'));
        self::assertSame('swap-1', $taken->id);
        self::assertEquals($offer->createdAt, $taken->createdAt);
        self::assertSame('Sick, sorry', $taken->note);

        $next = $taken->apply($roster, Shifts::at('07:30:00'));
        self::assertEquals([new PlaceTransferred('desk-am', 'cy', 'ada')], $next->changes);
        self::assertSame(PlaceStatus::Confirmed, $next->statusOf('cy'));
        self::assertNull($next->statusOf('ada'));
        self::assertTrue($taken->isStale($next));
    }

    public function testAPlaceWaitsForACoordinatorWhenTheShiftAsks(): void
    {
        $roster = $this->roster(true);
        $offer = SwapOffer::offer('swap-1', $roster, 'ada', '', Shifts::at('07:00:00'));
        $taken = $offer->take($roster, 'cy', ['FIRST_AID'], Shifts::at('07:30:00'));

        self::assertSame(SwapStatus::Pending, $taken->status);
        self::assertNull($taken->decidedAt);
        $this->assertRefused(ShiftRefused::SWAP_NOT_PENDING, static fn (): Roster => $taken->apply($roster, Shifts::at('07:30:00')));
        $this->assertRefused(ShiftRefused::SWAP_NOT_OPEN, static fn (): SwapOffer => $taken->take($roster, 'dee', ['FIRST_AID'], Shifts::at('07:30:00')));

        $approved = $taken->approve($roster, 'grace', Shifts::at('08:00:00'));
        self::assertSame(SwapStatus::Completed, $approved->status);
        self::assertSame('grace', $approved->decidedBy);
        self::assertSame('cy', $approved->takenBy);
        self::assertSame('2026-11-28 08:00:00', $approved->decidedAt?->format('Y-m-d H:i:s'));
        self::assertSame(PlaceStatus::Confirmed, $approved->apply($roster, Shifts::at('08:00:00'))->statusOf('cy'));
        $this->assertRefused(ShiftRefused::SWAP_NOT_PENDING, static fn (): SwapOffer => $approved->approve($roster, 'grace', Shifts::at('08:00:00')));
        $this->assertRefused(ShiftRefused::SWAP_NOT_PENDING, static fn (): SwapOffer => $offer->approve($roster, 'grace', Shifts::at('08:00:00')));

        $refused = $taken->refuse('grace', Shifts::at('08:00:00'));
        self::assertSame(SwapStatus::Open, $refused->status);
        self::assertNull($refused->takenBy);
        self::assertSame('grace', $refused->decidedBy);
        self::assertSame('2026-11-28 08:00:00', $refused->decidedAt?->format('Y-m-d H:i:s'));
        $this->assertRefused(ShiftRefused::SWAP_NOT_PENDING, static fn (): SwapOffer => $refused->refuse('grace', Shifts::at('08:00:00')));
        $this->assertRefused(ShiftRefused::CLOSED, static fn (): SwapOffer => $taken->approve($roster, 'grace', Shifts::at('09:00:00')));
        $this->assertRefused(ShiftRefused::SWAP_STALE, static fn (): SwapOffer => $taken->approve($roster->withdraw('ada', Shifts::at('08:00:00')), 'grace', Shifts::at('08:00:00')));

        $this->expectException(InvalidShift::class);
        $taken->refuse('', Shifts::at('08:00:00'));
    }

    public function testWhoMayOfferAndTake(): void
    {
        $roster = $this->roster(true);
        $this->assertRefused(ShiftRefused::NOT_CONFIRMED, static fn (): SwapOffer => SwapOffer::offer('s', $roster, 'cy', '', Shifts::at('07:00:00')));
        $this->assertRefused(ShiftRefused::CLOSED, static fn (): SwapOffer => SwapOffer::offer('s', $roster, 'ada', '', Shifts::at('09:00:00')));

        $offer = SwapOffer::offer('s', $roster, 'ada', '', Shifts::at('07:00:00'));
        $this->assertRefused(ShiftRefused::SWAP_OWN_OFFER, static fn (): SwapOffer => $offer->take($roster, 'ada', ['FIRST_AID'], Shifts::at('07:00:00')));
        $this->assertRefused(ShiftRefused::ROLE_REQUIRED, static fn (): SwapOffer => $offer->take($roster, 'cy', [], Shifts::at('07:00:00')));
        $this->assertRefused(ShiftRefused::ALREADY_ON_ROSTER, static fn (): SwapOffer => $offer->take($roster, 'bob', ['FIRST_AID'], Shifts::at('07:00:00')));
        $this->assertRefused(ShiftRefused::CLOSED, static fn (): SwapOffer => $offer->take($roster, 'cy', ['FIRST_AID'], Shifts::at('09:00:00')));
        $this->assertRefused(ShiftRefused::SWAP_OTHER_SHIFT, static fn (): SwapOffer => $offer->take(Roster::open(Shifts::desk(id: 'other')), 'cy', ['FIRST_AID'], Shifts::at('07:00:00')));
        $this->assertRefused(ShiftRefused::SWAP_STALE, static fn (): SwapOffer => $offer->take($roster->withdraw('ada', Shifts::at('07:00:00')), 'cy', ['FIRST_AID'], Shifts::at('07:00:00')));
        self::assertTrue($offer->isStale(Roster::open(Shifts::desk(id: 'other'))));

        $this->expectException(InvalidShift::class);
        $offer->take($roster, '', [], Shifts::at('07:00:00'));
    }

    public function testACoordinatorIsAnIdentifierBeforeAnythingElse(): void
    {
        $roster = $this->roster(true);
        $offer = SwapOffer::offer('s', $roster, 'ada', '', Shifts::at('07:00:00'));
        $taken = $offer->take($roster, 'cy', ['FIRST_AID'], Shifts::at('07:30:00'));

        foreach ([
            static fn (): SwapOffer => $offer->refuse('', Shifts::at('08:00:00')),
            static fn (): SwapOffer => $taken->refuse(' grace', Shifts::at('08:00:00')),
            static fn (): SwapOffer => $taken->approve($roster, '', Shifts::at('08:00:00')),
        ] as $operation) {
            try {
                $operation();
                self::fail('The coordinator is refused.');
            } catch (InvalidShift) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testACompletedOfferAppliesOnlyToTheRosterItWasMadeOn(): void
    {
        $roster = $this->roster(false);
        $completed = SwapOffer::offer('s', $roster, 'ada', '', Shifts::at('07:00:00'))->take($roster, 'cy', [], Shifts::at('07:30:00'));

        $this->assertRefused(ShiftRefused::SWAP_OTHER_SHIFT, static fn (): Roster => $completed->apply(Roster::open(Shifts::desk(id: 'other')), Shifts::at('07:30:00')));
        $this->assertRefused(ShiftRefused::CLOSED, static fn (): Roster => $completed->apply($roster, Shifts::at('09:00:00')));
        $this->assertRefused(ShiftRefused::SWAP_STALE, static fn (): Roster => $completed->apply($roster->withdraw('ada', Shifts::at('07:00:00')), Shifts::at('07:30:00')));
    }

    public function testTheVolunteerWhoOfferedCancels(): void
    {
        $roster = $this->roster(true);
        $offer = SwapOffer::offer('s', $roster, 'ada', '', Shifts::at('07:00:00'));
        $cancelled = $offer->cancel('ada', Shifts::at('07:10:00'));

        self::assertSame(SwapStatus::Cancelled, $cancelled->status);
        self::assertNull($cancelled->takenBy);
        self::assertSame('ada', $cancelled->decidedBy);
        self::assertSame('2026-11-28 07:10:00', $cancelled->decidedAt?->format('Y-m-d H:i:s'));
        self::assertSame('cy', $offer->take($roster, 'cy', ['FIRST_AID'], Shifts::at('07:20:00'))->cancel('ada', Shifts::at('07:30:00'))->takenBy);
        $this->assertRefused(ShiftRefused::SWAP_NOT_YOURS, static fn (): SwapOffer => $offer->cancel('bob', Shifts::at('07:10:00')));
        $this->assertRefused(ShiftRefused::SWAP_NOT_OPEN, static fn (): SwapOffer => $cancelled->cancel('ada', Shifts::at('07:10:00')));
        $this->assertRefused(ShiftRefused::SWAP_NOT_PENDING, static fn (): Roster => $cancelled->apply($roster, Shifts::at('07:10:00')));
    }

    public function testTheBoundsOfAnOffer(): void
    {
        $at = new DateTimeImmutable('2026-11-28T07:00:00-05:00');

        self::assertSame('2026-11-28 12:00:00', new SwapOffer('s', 'd', 'ada', $at)->createdAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-28 12:00:00', new SwapOffer('s', 'd', 'ada', $at, '', SwapStatus::Completed, 'bob', 'grace', $at)->decidedAt?->format('Y-m-d H:i:s'));
        self::assertSame(str_repeat('n', 500), new SwapOffer('s', 'd', 'ada', $at, str_repeat('n', 500))->note);

        foreach ([
            static fn (): SwapOffer => new SwapOffer('S', 'd', 'ada', $at),
            static fn (): SwapOffer => new SwapOffer('s', 'D', 'ada', $at),
            static fn (): SwapOffer => new SwapOffer('s', 'd', '', $at),
            static fn (): SwapOffer => new SwapOffer('s', 'd', 'ada', $at, str_repeat('n', 501)),
            static fn (): SwapOffer => new SwapOffer('s', 'd', 'ada', $at, '', SwapStatus::Open, 'bob'),
            static fn (): SwapOffer => new SwapOffer('s', 'd', 'ada', $at, '', SwapStatus::Pending),
            static fn (): SwapOffer => new SwapOffer('s', 'd', 'ada', $at, '', SwapStatus::Completed),
            static fn (): SwapOffer => new SwapOffer('s', 'd', 'ada', $at, '', SwapStatus::Pending, ''),
            static fn (): SwapOffer => new SwapOffer('s', 'd', 'ada', $at, '', SwapStatus::Cancelled, null, ''),
        ] as $index => $build) {
            try {
                $build();
                self::fail(\sprintf('Offer %d is refused.', $index));
            } catch (InvalidShift) {
                self::addToAssertionCount(1);
            }
        }

        self::assertNull(new SwapOffer('s', 'd', 'ada', $at, '', SwapStatus::Cancelled)->takenBy);
        self::assertSame('bob', new SwapOffer('s', 'd', 'ada', $at, '', SwapStatus::Cancelled, 'bob')->takenBy);
    }

    public function testTheActiveStatuses(): void
    {
        self::assertTrue(SwapStatus::Open->isActive());
        self::assertTrue(SwapStatus::Pending->isActive());
        self::assertFalse(SwapStatus::Completed->isActive());
        self::assertFalse(SwapStatus::Cancelled->isActive());
    }

    public function testTheSwapEventsCarryIdentifiers(): void
    {
        $offered = new SwapOffered('s', 'desk-am', 'ada');
        $taken = new SwapTaken('s', 'desk-am', 'cy');

        self::assertSame(['s', 'desk-am', 'ada'], [$offered->offerId, $offered->shiftId, $offered->volunteerId]);
        self::assertSame(['s', 'desk-am', 'cy'], [$taken->offerId, $taken->shiftId, $taken->volunteerId]);
    }

    private function roster(bool $approval): Roster
    {
        return new Roster(Shifts::desk(capacity: 2, reserve: 1, role: $approval ? 'FIRST_AID' : null, approval: $approval), [
            new Place('ada', PlaceStatus::Confirmed, Shifts::at('06:00:00')),
            new Place('bob', PlaceStatus::Confirmed, Shifts::at('06:00:00')),
            new Place('cy', PlaceStatus::Reserve, Shifts::at('06:00:00')),
        ]);
    }

    /**
     * @param Closure(): mixed $operation
     */
    private function assertRefused(string $reason, Closure $operation): void
    {
        try {
            $operation();
            self::fail('The operation is refused.');
        } catch (ShiftRefused $shiftRefused) {
            self::assertSame($reason, $shiftRefused->reason);
        }
    }
}
