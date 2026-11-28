<?php

declare(strict_types=1);

namespace Phil\Shifts\Tests\Unit\Roster;

use Closure;
use Phil\Shifts\Allocation\FairAllocator;
use Phil\Shifts\Allocation\InterestAllocator;
use Phil\Shifts\Event\InterestExpressed;
use Phil\Shifts\Event\PlaceTransferred;
use Phil\Shifts\Event\ReservePromoted;
use Phil\Shifts\Event\VolunteerConfirmed;
use Phil\Shifts\Event\VolunteerReserved;
use Phil\Shifts\Event\VolunteerWithdrew;
use Phil\Shifts\Exception\InvalidShift;
use Phil\Shifts\Exception\ShiftRefused;
use Phil\Shifts\Roster\Conflicts;
use Phil\Shifts\Roster\Place;
use Phil\Shifts\Roster\PlaceStatus;
use Phil\Shifts\Roster\Roster;
use Phil\Shifts\Shift\AssignmentMode;
use Phil\Shifts\Shift\Shift;
use Phil\Shifts\Shift\ShiftKind;
use Phil\Shifts\Tests\Fixtures\Shifts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Roster::class)]
#[CoversClass(Place::class)]
#[CoversClass(Conflicts::class)]
#[CoversClass(FairAllocator::class)]
#[CoversClass(ShiftRefused::class)]
#[CoversClass(VolunteerConfirmed::class)]
#[CoversClass(VolunteerReserved::class)]
#[CoversClass(InterestExpressed::class)]
#[CoversClass(VolunteerWithdrew::class)]
#[CoversClass(ReservePromoted::class)]
#[CoversClass(PlaceTransferred::class)]
final class RosterTest extends TestCase
{
    public function testSignUpsTakeThePlacesThenTheReserve(): void
    {
        $roster = Roster::open(Shifts::desk());
        self::assertSame([], $roster->places);
        self::assertSame([], $roster->changes);
        self::assertSame(2, $roster->placesLeft());

        $roster = $roster->signUp('ada', [], Shifts::at('08:00:00'));
        self::assertEquals([new VolunteerConfirmed('desk-am', 'ada')], $roster->changes);
        self::assertSame(1, $roster->placesLeft());
        $roster = $roster->signUp('bob', [], Shifts::at('08:01:00'))->signUp('cy', [], Shifts::at('08:02:00'));
        self::assertEquals([new VolunteerReserved('desk-am', 'cy')], $roster->changes);
        self::assertSame(['ada', 'bob'], $this->volunteers($roster->confirmed()));
        self::assertSame(['cy'], $this->volunteers($roster->reserve()));
        self::assertSame(PlaceStatus::Reserve, $roster->statusOf('cy'));
        self::assertNull($roster->statusOf('dee'));
        self::assertSame(0, $roster->placesLeft());
        self::assertSame('2026-11-28 08:02:00 UTC', $roster->reserve()[0]->at->format('Y-m-d H:i:s T'));

        $this->assertRefused(ShiftRefused::FULL, static fn (): Roster => $roster->signUp('dee', [], Shifts::at('08:03:00')));
        $this->assertRefused(ShiftRefused::ALREADY_ON_ROSTER, static fn (): Roster => $roster->signUp('cy', [], Shifts::at('08:03:00')));
    }

    public function testASignUpIsRefusedOutsideItsModeAfterTheStartOrWithoutTheRole(): void
    {
        $this->assertRefused(ShiftRefused::WRONG_MODE, static fn (): Roster => Roster::open(Shifts::desk(mode: AssignmentMode::Assigned))->signUp('ada', [], Shifts::at('08:00:00')));
        $this->assertRefused(ShiftRefused::WRONG_MODE, static fn (): Roster => Roster::open(Shifts::desk(mode: AssignmentMode::Interest))->signUp('ada', [], Shifts::at('08:00:00')));
        $this->assertRefused(ShiftRefused::CLOSED, static fn (): Roster => Roster::open(Shifts::desk())->signUp('ada', [], Shifts::at('09:00:00')));
        $this->assertRefused(ShiftRefused::ROLE_REQUIRED, static fn (): Roster => Roster::open(Shifts::desk(role: 'FIRST_AID'))->signUp('ada', ['CASHIER'], Shifts::at('08:00:00')));
        self::assertSame(PlaceStatus::Confirmed, Roster::open(Shifts::desk(role: 'FIRST_AID'))->signUp('ada', ['FIRST_AID'], Shifts::at('08:00:00'))->statusOf('ada'));
        self::assertSame(PlaceStatus::Confirmed, Roster::open(Shifts::desk())->signUp('ada', [], Shifts::at('08:59:59'))->statusOf('ada'));

        $this->expectException(InvalidShift::class);
        Roster::open(Shifts::desk())->signUp(' ada', [], Shifts::at('08:00:00'));
    }

    public function testAFullShiftWithoutReserveRefuses(): void
    {
        $roster = Roster::open(Shifts::desk(capacity: 1, reserve: 0))->signUp('ada', [], Shifts::at('08:00:00'));

        $this->assertRefused(ShiftRefused::FULL, static fn (): Roster => $roster->signUp('bob', [], Shifts::at('08:00:00')));
    }

    public function testAWithdrawalFreesAPlaceForTheFirstOfTheReserve(): void
    {
        $shift = Shifts::desk(capacity: 1, reserve: 3);
        $roster = new Roster($shift, [
            new Place('ada', PlaceStatus::Confirmed, Shifts::at('07:00:00')),
            new Place('cy', PlaceStatus::Reserve, Shifts::at('07:01:00')),
            new Place('bob', PlaceStatus::Reserve, Shifts::at('07:02:00')),
        ]);

        $next = $roster->withdraw('ada', Shifts::at('08:00:00'));
        self::assertEquals([new VolunteerWithdrew('desk-am', 'ada'), new ReservePromoted('desk-am', 'cy')], $next->changes);
        self::assertSame(['cy'], $this->volunteers($next->confirmed()));
        self::assertSame('2026-11-28 08:00:00', $next->confirmed()[0]->at->format('Y-m-d H:i:s'));
        self::assertSame(['bob'], $this->volunteers($next->reserve()));
        self::assertSame([0, 1], array_keys($next->places));

        $fromReserve = $roster->withdraw('bob', Shifts::at('08:00:00'));
        self::assertEquals([new VolunteerWithdrew('desk-am', 'bob')], $fromReserve->changes);
        self::assertSame(['ada', 'cy'], $this->volunteers($fromReserve->places));

        $empty = Roster::open($shift)->signUp('ada', [], Shifts::at('07:00:00'))->withdraw('ada', Shifts::at('07:30:00'));
        self::assertSame([], $empty->places);
        self::assertEquals([new VolunteerWithdrew('desk-am', 'ada')], $empty->changes);

        $this->assertRefused(ShiftRefused::NOT_ON_ROSTER, static fn (): Roster => $roster->withdraw('dee', Shifts::at('08:00:00')));
        $this->assertRefused(ShiftRefused::CLOSED, static fn (): Roster => $roster->withdraw('ada', Shifts::at('09:00:00')));
    }

    public function testACoordinatorAssignsInEveryMode(): void
    {
        $roster = Roster::open(Shifts::desk(capacity: 2, reserve: 1, mode: AssignmentMode::Assigned, role: 'FIRST_AID'));
        $roster = $roster->assign('ada', 'grace', Shifts::at('08:00:00'));
        self::assertEquals([new VolunteerConfirmed('desk-am', 'ada', 'grace')], $roster->changes);
        self::assertSame('grace', $roster->confirmed()[0]->assignedBy);

        $reserve = new Roster(Shifts::desk(capacity: 2, reserve: 1), [new Place('ada', PlaceStatus::Confirmed, Shifts::at('07:00:00')), new Place('bob', PlaceStatus::Reserve, Shifts::at('07:00:00'))]);
        $promoted = $reserve->assign('bob', 'grace', Shifts::at('08:00:00'));
        self::assertSame(['ada', 'bob'], $this->volunteers($promoted->confirmed()));
        self::assertSame([], $promoted->reserve());

        $this->assertRefused(ShiftRefused::ALREADY_ON_ROSTER, static fn (): Roster => $roster->assign('ada', 'grace', Shifts::at('08:00:00')));
        $full = $roster->assign('bob', 'grace', Shifts::at('08:00:00'));
        $this->assertRefused(ShiftRefused::FULL, static fn (): Roster => $full->assign('cy', 'grace', Shifts::at('08:00:00')));
        $this->assertRefused(ShiftRefused::CLOSED, static fn (): Roster => $roster->assign('cy', 'grace', Shifts::at('09:00:00')));

        $this->expectException(InvalidShift::class);
        $roster->assign('cy', '', Shifts::at('08:00:00'));
    }

    public function testInterestIsAllocatedFairly(): void
    {
        $roster = Roster::open(Shifts::cloakroom());
        $this->assertRefused(ShiftRefused::WRONG_MODE, static fn (): Roster => Roster::open(Shifts::desk())->expressInterest('ada', [], Shifts::at('07:00:00')));

        foreach (['ada' => '07:00:00', 'bob' => '07:01:00', 'cy' => '07:02:00', 'dee' => '07:02:00', 'eve' => '07:03:00', 'fay' => '07:04:00'] as $volunteer => $time) {
            $roster = $roster->expressInterest($volunteer, [], Shifts::at($time));
        }

        self::assertEquals([new InterestExpressed('cloakroom', 'fay')], $roster->changes);
        self::assertCount(6, $roster->interested());
        self::assertTrue($roster->isUnderstaffed());
        $this->assertRefused(ShiftRefused::ALREADY_ON_ROSTER, static fn (): Roster => $roster->expressInterest('ada', [], Shifts::at('07:05:00')));

        $roster = $roster->assign('fay', 'grace', Shifts::at('07:30:00'));
        $allocated = $roster->allocate(new FairAllocator(), ['ada' => 300, 'bob' => 0, 'eve' => 60], Shifts::at('08:00:00'));
        self::assertSame(['fay', 'bob', 'cy', 'dee', 'eve'], $this->volunteers($allocated->confirmed()));
        self::assertSame(['ada'], $this->volunteers($allocated->interested()));
        self::assertEquals([new VolunteerConfirmed('cloakroom', 'bob'), new VolunteerConfirmed('cloakroom', 'cy'), new VolunteerConfirmed('cloakroom', 'dee'), new VolunteerConfirmed('cloakroom', 'eve')], $allocated->changes);
        self::assertFalse($allocated->isUnderstaffed());

        $again = $allocated->allocate(new FairAllocator(), [], Shifts::at('08:00:00'));
        self::assertSame([], $again->changes);
        self::assertSame($allocated->places, $again->places);
        self::assertSame([], Roster::open(Shifts::cloakroom())->allocate(new FairAllocator(), [], Shifts::at('08:00:00'))->changes);

        $this->assertRefused(ShiftRefused::WRONG_MODE, static fn (): Roster => Roster::open(Shifts::desk())->allocate(new FairAllocator(), [], Shifts::at('08:00:00')));
        $this->assertRefused(ShiftRefused::CLOSED, static fn (): Roster => $roster->allocate(new FairAllocator(), [], Shifts::at('20:00:00')));
    }

    public function testARosterKeepsOnlyWhatTheAllocatorMayChoose(): void
    {
        $roster = Roster::open(new Shift('c', 'C', ShiftKind::Task, Shifts::at('08:00:00'), Shifts::at('20:00:00'), 60, 2, AssignmentMode::Interest))
            ->expressInterest('ada', [], Shifts::at('07:00:00'))
            ->expressInterest('bob', [], Shifts::at('07:00:00'))
            ->expressInterest('cy', [], Shifts::at('07:00:00'));
        $allocator = new class implements InterestAllocator {
            public int $places = 0;

            public function choose(array $interested, int $places, array $minutesHeld): array
            {
                $this->places = $places;

                return ['stranger', 'cy', 'cy', 'bob', 'ada'];
            }
        };

        $allocated = $roster->allocate($allocator, [], Shifts::at('08:00:00'));
        self::assertSame(2, $allocator->places);
        self::assertSame(['cy', 'bob'], $this->volunteers($allocated->confirmed()));
        self::assertSame(['ada'], $this->volunteers($allocated->interested()));
    }

    public function testThePlacesOfARosterRespectTheShift(): void
    {
        $at = Shifts::at('07:00:00');
        $this->assertInvalid(static fn (): Roster => new Roster(Shifts::desk(), [new Place('ada', PlaceStatus::Confirmed, $at), new Place('ada', PlaceStatus::Reserve, $at)]), 'A volunteer stands once on a roster.');
        $this->assertInvalid(static fn (): Roster => new Roster(Shifts::desk(capacity: 1), [new Place('ada', PlaceStatus::Confirmed, $at), new Place('bob', PlaceStatus::Confirmed, $at)]), 'A roster holds no more places');
        $this->assertInvalid(static fn (): Roster => new Roster(Shifts::desk(reserve: 0), [new Place('ada', PlaceStatus::Reserve, $at)]), 'A roster holds no more places');
        $this->assertInvalid(static fn (): Roster => new Roster(Shifts::desk(), array_map(static fn (int $i): Place => new Place('v'.$i, PlaceStatus::Interested, $at), range(1, 1001))), 'A roster holds no more places');
        self::assertCount(1000, new Roster(Shifts::desk(), array_map(static fn (int $i): Place => new Place('v'.$i, PlaceStatus::Interested, $at), range(1, 1000)))->interested());
        self::assertCount(3, new Roster(Shifts::desk(), [new Place('ada', PlaceStatus::Confirmed, $at), new Place('bob', PlaceStatus::Confirmed, $at), new Place('cy', PlaceStatus::Reserve, $at)])->places);
    }

    public function testOneThousandInterestedAtMost(): void
    {
        $at = Shifts::at('07:00:00');
        $roster = new Roster(Shifts::cloakroom(), array_map(static fn (int $i): Place => new Place('v'.$i, PlaceStatus::Interested, $at), range(1, 1000)));

        $this->assertRefused(ShiftRefused::FULL, static fn (): Roster => $roster->expressInterest('ada', [], $at));
        self::assertCount(1000, new Roster(Shifts::cloakroom(), \array_slice($roster->places, 1))->expressInterest('ada', [], $at)->interested());
    }

    public function testACorrectedShiftKeepsItsVolunteers(): void
    {
        $roster = Roster::open(Shifts::desk())->signUp('ada', [], Shifts::at('07:00:00'))->signUp('bob', [], Shifts::at('07:00:00'));

        self::assertSame(['ada', 'bob'], $this->volunteers($roster->withShift(Shifts::desk(capacity: 3))->confirmed()));
        self::assertSame([], $roster->withShift(Shifts::desk(capacity: 3))->changes);
        $this->assertInvalid(static fn (): Roster => $roster->withShift(Shifts::desk(capacity: 1)), 'A roster holds no more places');
        $this->assertInvalid(static fn (): Roster => $roster->withShift(Shifts::desk(id: 'other')), 'A roster keeps the identifier of its shift.');
    }

    public function testAPlaceTransfers(): void
    {
        $roster = new Roster(Shifts::desk(capacity: 2, reserve: 1), [
            new Place('ada', PlaceStatus::Confirmed, Shifts::at('07:00:00')),
            new Place('bob', PlaceStatus::Confirmed, Shifts::at('07:00:00')),
            new Place('cy', PlaceStatus::Reserve, Shifts::at('07:00:00')),
        ]);

        $next = $roster->transfer('ada', 'cy', Shifts::at('08:00:00'));
        self::assertEquals([new PlaceTransferred('desk-am', 'cy', 'ada')], $next->changes);
        self::assertSame(['bob', 'cy'], $this->volunteers($next->confirmed()));
        self::assertSame([], $next->reserve());
        self::assertNull($next->statusOf('ada'));
        self::assertSame(['bob', 'dee'], $this->volunteers($roster->transfer('ada', 'dee', Shifts::at('08:00:00'))->confirmed()));

        $this->assertRefused(ShiftRefused::NOT_CONFIRMED, static fn (): Roster => $roster->transfer('cy', 'dee', Shifts::at('08:00:00')));
        $this->assertRefused(ShiftRefused::ALREADY_ON_ROSTER, static fn (): Roster => $roster->transfer('ada', 'bob', Shifts::at('08:00:00')));
        $this->assertRefused(ShiftRefused::CLOSED, static fn (): Roster => $roster->transfer('ada', 'dee', Shifts::at('09:00:00')));

        $this->expectException(InvalidShift::class);
        $roster->transfer('ada', '', Shifts::at('08:00:00'));
    }

    public function testACriticalShiftIsUnderstaffedUntilFull(): void
    {
        self::assertFalse(Roster::open(Shifts::desk())->isUnderstaffed());
        self::assertTrue(Roster::open(Shifts::cloakroom())->isUnderstaffed());
    }

    public function testTheConflictsOfAVolunteer(): void
    {
        $morning = Shifts::desk();
        $noon = Shifts::desk(id: 'noon', from: '12:00:00', to: '13:00:00');
        $late = Shifts::desk(id: 'late', from: '12:30:00', to: '13:00:00');
        $inside = Shifts::desk(id: 'inside', from: '10:00:00', to: '11:00:00');

        self::assertSame([$inside], new Conflicts()->of($morning, [$morning, $noon, $inside, Shifts::cloakroom()]));
        self::assertSame([$noon, $inside], new Conflicts(30)->of($morning, [$noon, $late, $inside]));
        self::assertSame([$noon, $late, $inside], new Conflicts(31)->of($morning, [$noon, $late, $inside]));
        self::assertSame(0, new Conflicts(-5)->restMinutes);
        self::assertSame(1440, new Conflicts(5000)->restMinutes);
        self::assertSame(30, new Conflicts(30)->restMinutes);
    }

    public function testAPlaceCarriesValidIdentifiers(): void
    {
        self::assertSame('grace', new Place('ada', PlaceStatus::Confirmed, Shifts::at('07:00:00'), 'grace')->assignedBy);

        foreach ([['', null], [' ada', null], ['ada', ''], ['ada', 'grace '], ['', ''], ['ada', "gr\nace"]] as [$volunteer, $assignedBy]) {
            try {
                new Place($volunteer, PlaceStatus::Confirmed, Shifts::at('07:00:00'), $assignedBy);
                self::fail('The place is refused.');
            } catch (InvalidShift) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testIdentifiersAreCheckedBeforeAnythingElse(): void
    {
        $closed = Shifts::at('09:00:00');
        $roster = new Roster(Shifts::desk(capacity: 2, reserve: 1), [new Place('ada', PlaceStatus::Confirmed, Shifts::at('07:00:00'))]);
        $assigned = Roster::open(Shifts::desk(mode: AssignmentMode::Assigned));

        foreach ([
            static fn (): Roster => $roster->assign('', 'grace', $closed),
            static fn (): Roster => $roster->assign('cy', '', $closed),
            static fn (): Roster => $roster->transfer('ada', '', $closed),
            static fn (): Roster => $roster->signUp(' cy', [], $closed),
            static fn (): Roster => $assigned->signUp(' cy', [], Shifts::at('08:00:00')),
            static fn (): Roster => $assigned->expressInterest('cy ', [], Shifts::at('08:00:00')),
        ] as $operation) {
            try {
                $operation();
                self::fail('The identifier is refused.');
            } catch (InvalidShift) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTheAllocatorIsAskedOnlyWhenThereIsSomethingToChoose(): void
    {
        $shift = new Shift('c', 'C', ShiftKind::Task, Shifts::at('08:00:00'), Shifts::at('20:00:00'), 60, 1, AssignmentMode::Interest);
        $allocator = new class implements InterestAllocator {
            public int $calls = 0;

            public function choose(array $interested, int $places, array $minutesHeld): array
            {
                ++$this->calls;

                return array_map(static fn (Place $place): string => $place->volunteer, $interested);
            }
        };

        $full = new Roster($shift, [new Place('ada', PlaceStatus::Confirmed, Shifts::at('07:00:00')), new Place('cy', PlaceStatus::Interested, Shifts::at('07:00:00'))]);
        $nobody = new Roster($shift);

        self::assertSame($full->places, $full->allocate($allocator, [], Shifts::at('08:00:00'))->places);
        self::assertSame([], $nobody->allocate($allocator, [], Shifts::at('08:00:00'))->places);
        self::assertSame(0, $allocator->calls);

        $last = new Roster($shift, [new Place('cy', PlaceStatus::Interested, Shifts::at('07:00:00'))]);
        self::assertSame(['cy'], $this->volunteers($last->allocate($allocator, [], Shifts::at('08:00:00'))->confirmed()));
        self::assertSame(1, $allocator->calls);
    }

    public function testEveryOperationReturnsAListOfPlaces(): void
    {
        $shift = new Shift('c', 'C', ShiftKind::Task, Shifts::at('08:00:00'), Shifts::at('20:00:00'), 60, 3, AssignmentMode::Interest);
        $roster = new Roster($shift, [
            new Place('ada', PlaceStatus::Interested, Shifts::at('07:00:00')),
            new Place('bob', PlaceStatus::Interested, Shifts::at('07:01:00')),
            new Place('cy', PlaceStatus::Confirmed, Shifts::at('07:02:00')),
            new Place('dee', PlaceStatus::Interested, Shifts::at('07:03:00')),
        ]);

        self::assertSame(['ada', 'cy', 'bob', 'dee'], $this->volunteers($roster->allocate(new FairAllocator(), ['ada' => 100], Shifts::at('08:00:00'))->places));
        self::assertSame([0, 1, 2, 3], array_keys($roster->allocate(new FairAllocator(), ['ada' => 100], Shifts::at('08:00:00'))->places));
        self::assertSame([0, 1, 2], array_keys($roster->transfer('cy', 'bob', Shifts::at('08:00:00'))->places));
        self::assertSame([0, 1, 2, 3], array_keys($roster->assign('bob', 'grace', Shifts::at('08:00:00'))->places));
        self::assertSame([0, 1, 2], array_keys($roster->withdraw('ada', Shifts::at('08:00:00'))->places));
    }

    public function testTheFairAllocatorPutsAnUnknownVolunteerBeforeOneWhoServedAMinute(): void
    {
        $interested = [
            new Place('bob', PlaceStatus::Interested, Shifts::at('07:00:00')),
            new Place('ada', PlaceStatus::Interested, Shifts::at('07:05:00')),
        ];

        self::assertSame(['ada', 'bob'], new FairAllocator()->choose($interested, 2, ['bob' => 1]));
    }

    public function testTheFairAllocatorOrder(): void
    {
        $at = Shifts::at(...);
        $interested = [
            new Place('eve', PlaceStatus::Interested, $at('07:00:00')),
            new Place('cy', PlaceStatus::Interested, $at('07:01:00')),
            new Place('bob', PlaceStatus::Interested, $at('07:01:00')),
            new Place('ada', PlaceStatus::Interested, $at('07:02:00')),
        ];

        self::assertSame(['eve', 'bob', 'cy', 'ada'], new FairAllocator()->choose($interested, 10, []));
        self::assertSame(['ada', 'eve'], new FairAllocator()->choose($interested, 2, ['eve' => 10, 'cy' => 20, 'bob' => 20]));
        self::assertSame(['eve'], new FairAllocator()->choose($interested, 1, []));
    }

    /**
     * @param list<Place> $places
     *
     * @return list<string>
     */
    private function volunteers(array $places): array
    {
        return array_map(static fn (Place $place): string => $place->volunteer, $places);
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
            self::assertSame(\sprintf('The operation is refused: %s.', $reason), $shiftRefused->getMessage());
        }
    }

    /**
     * @param Closure(): mixed $operation
     * @param non-empty-string $message
     */
    private function assertInvalid(Closure $operation, string $message): void
    {
        try {
            $operation();
            self::fail('The roster is refused.');
        } catch (InvalidShift $invalidShift) {
            self::assertStringStartsWith($message, $invalidShift->getMessage());
        }
    }
}
