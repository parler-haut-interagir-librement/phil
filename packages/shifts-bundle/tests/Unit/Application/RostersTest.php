<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Unit\Application;

use Closure;
use Phil\Shifts\Allocation\FairAllocator;
use Phil\Shifts\Event\InterestExpressed;
use Phil\Shifts\Event\ReservePromoted;
use Phil\Shifts\Event\VolunteerConfirmed;
use Phil\Shifts\Event\VolunteerReserved;
use Phil\Shifts\Event\VolunteerWithdrew;
use Phil\Shifts\Exception\ShiftRefused;
use Phil\Shifts\Roster\Conflicts;
use Phil\Shifts\Roster\Place;
use Phil\Shifts\Roster\PlaceStatus;
use Phil\Shifts\Roster\Roster;
use Phil\Shifts\Shift\AssignmentMode;
use Phil\Shifts\Shift\Shift;
use Phil\Shifts\Shift\ShiftKind;
use Phil\ShiftsBundle\Application\Exception\RequestRefused;
use Phil\ShiftsBundle\Application\Message\AllocateInterest;
use Phil\ShiftsBundle\Application\Rosters;
use Phil\ShiftsBundle\Infrastructure\Messenger\AllocateInterestHandler;
use Phil\ShiftsBundle\Tests\Fixtures\RacingRosterRepository;
use Phil\ShiftsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

#[CoversClass(Rosters::class)]
#[CoversClass(RequestRefused::class)]
#[CoversClass(AllocateInterestHandler::class)]
#[CoversClass(AllocateInterest::class)]
final class RostersTest extends TestCase
{
    public function testAShiftIsPlannedOnceAndCorrected(): void
    {
        $stage = new Stage();
        $roster = $stage->rosters->plan(Stage::desk());

        self::assertSame([], $roster->places);
        $this->assertRequestRefused(RequestRefused::EXISTS, static fn (): Roster => $stage->rosters->plan(Stage::desk()));
        $stage->rosters->signUp('desk-am', 'ada', []);

        $corrected = $stage->rosters->correct(Stage::desk(capacity: 4));
        self::assertSame(4, $corrected->shift->capacity);
        self::assertSame(PlaceStatus::Confirmed, $stage->rosters->find('desk-am')?->statusOf('ada'));
        $this->assertRequestRefused(RequestRefused::UNKNOWN_SHIFT, static fn (): Roster => $stage->rosters->correct(Stage::desk('missing')));
        self::assertSame(['desk-am'], array_map(static fn (Roster $roster): string => $roster->shift->id, $stage->rosters->upcoming()));

        $stage->rosters->remove('desk-am');
        self::assertNull($stage->rosters->find('desk-am'));
        self::assertSame('The operation is refused: shifts.unknown_shift.', new RequestRefused(RequestRefused::UNKNOWN_SHIFT)->getMessage());
    }

    public function testVolunteersSignUpWithdrawAndTheEventsAreDispatchedOnceStored(): void
    {
        $stage = new Stage();
        $stage->rosters->plan(Stage::desk(capacity: 1, reserve: 1));

        $stage->rosters->signUp('desk-am', 'ada', []);
        $stage->rosters->signUp('desk-am', 'bob', []);
        $stage->rosters->withdraw('desk-am', 'ada');

        self::assertEquals([
            new VolunteerConfirmed('desk-am', 'ada'),
            new VolunteerReserved('desk-am', 'bob'),
            new VolunteerWithdrew('desk-am', 'ada'),
            new ReservePromoted('desk-am', 'bob'),
        ], $stage->events());
        self::assertSame(4, $stage->rosterRepository->find('desk-am')?->revision);
        self::assertSame(['desk-am'], array_map(static fn (Roster $roster): string => $roster->shift->id, $stage->rosters->ofVolunteer('bob')));
        $this->assertRequestRefused(RequestRefused::UNKNOWN_SHIFT, static fn (): Roster => $stage->rosters->signUp('missing', 'ada', []));
    }

    public function testAVolunteerNeverHoldsTwoFixedShiftsThatOverlap(): void
    {
        $stage = new Stage(restMinutes: 30);
        $stage->rosters->plan(Stage::desk());
        $stage->rosters->plan(Stage::desk('noon', '12:15:00', '13:00:00'));
        $stage->rosters->plan(Stage::desk('later', '12:30:00', '13:00:00'));
        $stage->rosters->plan(Stage::desk('assigned', '11:00:00', '13:00:00', mode: AssignmentMode::Assigned));
        $stage->rosters->signUp('desk-am', 'ada', []);

        $this->assertShiftRefused(ShiftRefused::CONFLICT, static fn (): Roster => $stage->rosters->signUp('noon', 'ada', []));
        self::assertSame(PlaceStatus::Confirmed, $stage->rosters->signUp('later', 'ada', [])->statusOf('ada'));
        $this->assertShiftRefused(ShiftRefused::CONFLICT, static fn (): Roster => $stage->rosters->assign('assigned', 'ada', 'grace'));
        self::assertSame('grace', $stage->rosters->assign('assigned', 'bob', 'grace')->confirmed()[0]->assignedBy);

        // A place in the reserve holds no shift.
        $stage->rosters->plan(Stage::desk('full', '15:00:00', '16:00:00', capacity: 1));
        $stage->rosters->plan(Stage::desk('overlap', '15:30:00', '16:30:00'));
        $stage->rosters->signUp('full', 'cy', []);
        $stage->rosters->signUp('full', 'dee', []);
        self::assertSame(PlaceStatus::Confirmed, $stage->rosters->signUp('overlap', 'dee', [])->statusOf('dee'));
    }

    public function testThePlacesAreAllocatedBalancedAndWithoutConflict(): void
    {
        $stage = new Stage();
        $stage->rosters->plan(Stage::cloakroom(capacity: 2));
        $stage->rosters->plan(Stage::desk('long', '08:00:00', '18:00:00', capacity: 5));
        $stage->rosters->plan(Stage::desk('other', '19:00:00', '20:00:00', capacity: 5));
        $stage->rosters->signUp('long', 'ada', []);

        foreach (['ada', 'bob', 'cy'] as $volunteer) {
            $stage->rosters->expressInterest('cloakroom', $volunteer, []);
        }

        $stage->rosters->signUp('other', 'bob', []);
        $stage->forget();

        $allocated = $stage->rosters->allocate('cloakroom');

        // ada holds 600 minutes elsewhere, bob 60 and cy none: a duration overlaps nothing, the load decides.
        self::assertSame(['cy', 'bob'], array_map(static fn (Place $place): string => $place->volunteer, $allocated->confirmed()));
        self::assertSame(['ada'], array_map(static fn (Place $place): string => $place->volunteer, $allocated->interested()));
        self::assertEquals([new VolunteerConfirmed('cloakroom', 'cy'), new VolunteerConfirmed('cloakroom', 'bob')], $stage->events());
        $this->assertShiftRefused(ShiftRefused::WRONG_MODE, static fn (): Roster => $stage->rosters->allocate('long'));
    }

    public function testAnInterestedVolunteerInAConflictIsLeftOut(): void
    {
        $stage = new Stage();
        $stage->rosters->plan(new Shift('pick', 'Pick', ShiftKind::Fixed, Stage::at('09:00:00'), Stage::at('10:00:00'), null, 2, AssignmentMode::Interest));
        $stage->rosters->plan(Stage::desk());
        $stage->rosters->expressInterest('pick', 'ada', []);
        $stage->rosters->expressInterest('pick', 'bob', []);
        $stage->rosters->signUp('desk-am', 'ada', []);

        $allocated = $stage->rosters->allocate('pick');

        self::assertSame(['bob'], array_map(static fn (Place $place): string => $place->volunteer, $allocated->confirmed()));
        self::assertSame(['ada'], array_map(static fn (Place $place): string => $place->volunteer, $allocated->interested()));
        self::assertSame(PlaceStatus::Interested, $stage->rosters->find('pick')?->statusOf('ada'));
    }

    public function testInterestRespectsTheRoles(): void
    {
        $stage = new Stage();
        $stage->rosters->plan(new Shift('aid', 'First aid', ShiftKind::Fixed, Stage::at('09:00:00'), Stage::at('10:00:00'), null, 2, AssignmentMode::Interest, 0, 'FIRST_AID'));

        $this->assertShiftRefused(ShiftRefused::ROLE_REQUIRED, static fn (): Roster => $stage->rosters->expressInterest('aid', 'ada', ['ROLE_USER']));
        self::assertEquals([new InterestExpressed('aid', 'ada')], $stage->rosters->expressInterest('aid', 'ada', ['FIRST_AID'])->changes);
    }

    public function testTheMinutesServedAndWhetherAShiftIsOpen(): void
    {
        $stage = new Stage();
        $roster = $stage->rosters->plan(Stage::desk());
        $stage->rosters->signUp('desk-am', 'ada', []);

        self::assertTrue($stage->rosters->isOpen($roster));
        self::assertSame(0, $stage->rosters->served('ada')->minutes);
        $stage->clock->modify('2026-11-28 12:00:00 UTC');
        self::assertFalse($stage->rosters->isOpen($roster));
        self::assertSame(180, $stage->rosters->served('ada')->minutes);
        self::assertSame([], $stage->rosters->upcoming());
    }

    public function testAWriteIsDoneAgainFromAFreshReadThenRefusedAsBusy(): void
    {
        $stage = new Stage();
        $stage->rosters->plan(Stage::desk());

        $racing = new RacingRosterRepository($stage->rosterRepository, 2);
        $rosters = new Rosters($racing, $stage->clock, new Conflicts(), new FairAllocator(), $stage);

        self::assertSame(PlaceStatus::Confirmed, $rosters->signUp('desk-am', 'ada', [])->statusOf('ada'));
        self::assertSame(3, $racing->reads);

        $busy = new Rosters(new RacingRosterRepository($stage->rosterRepository, 3), $stage->clock, new Conflicts(), new FairAllocator());
        $this->assertRequestRefused(RequestRefused::BUSY, static fn (): Roster => $busy->signUp('desk-am', 'bob', []));
        self::assertNull($stage->rosters->find('desk-am')?->statusOf('bob'));
    }

    public function testTheOthersAreReadFiveHundredAtATime(): void
    {
        $stage = new Stage();
        $stage->rosters->plan(new Shift('pick', 'Pick', ShiftKind::Task, Stage::at('08:00:00'), Stage::at('20:00:00'), 60, 1, AssignmentMode::Interest, 1));
        $stage->rosters->expressInterest('pick', 'ada', []);
        $stage->rosters->expressInterest('pick', 'bob', []);
        $stage->rosters->expressInterest('pick', 'cy', []);
        $stage->rosters->assign('pick', 'ada', 'grace');

        $racing = new RacingRosterRepository($stage->rosterRepository, 0);
        $rosters = new Rosters($racing, $stage->clock, new Conflicts(), new FairAllocator());

        // ada holds the only place: nobody moves.
        $same = $rosters->allocate('pick');
        self::assertSame(500, $racing->limit);
        self::assertSame(['ada'], array_map(static fn (Place $place): string => $place->volunteer, $same->confirmed()));
        self::assertSame(['bob', 'cy'], array_map(static fn (Place $place): string => $place->volunteer, $same->interested()));

        $rosters->upcoming();
        self::assertSame(200, $racing->limit);
        $rosters->upcoming(7);
        self::assertSame(7, $racing->limit);
    }

    public function testTheReserveKeepsItsPlacesWhenTheInterestedAreAllocated(): void
    {
        $stage = new Stage();
        $stage->rosters->plan(new Shift('mix', 'Mix', ShiftKind::Task, Stage::at('08:00:00'), Stage::at('20:00:00'), 60, 2, AssignmentMode::Interest, 2));
        $stage->rosterRepository->update(new Roster(new Shift('mix', 'Mix', ShiftKind::Task, Stage::at('08:00:00'), Stage::at('20:00:00'), 60, 2, AssignmentMode::Interest, 2), [
            new Place('ann', PlaceStatus::Reserve, Stage::at('07:00:00')),
            new Place('ben', PlaceStatus::Confirmed, Stage::at('07:00:00')),
            new Place('cal', PlaceStatus::Interested, Stage::at('07:01:00')),
            new Place('dan', PlaceStatus::Interested, Stage::at('07:02:00')),
        ]), 1);

        $allocated = $stage->rosters->allocate('mix');

        self::assertSame(['ann'], array_map(static fn (Place $place): string => $place->volunteer, $allocated->reserve()));
        self::assertSame(['ben', 'cal'], array_map(static fn (Place $place): string => $place->volunteer, $allocated->confirmed()));
        self::assertSame(['dan'], array_map(static fn (Place $place): string => $place->volunteer, $allocated->interested()));
        self::assertSame([0, 1, 2, 3], array_keys($allocated->places));
    }

    public function testTheEventsOfAChangeAreDispatchedByAnyone(): void
    {
        $stage = new Stage();
        $roster = Roster::open(Stage::desk())->signUp('ada', [], Stage::at('08:00:00'));
        $stage->rosters->dispatch($roster);

        self::assertEquals([new VolunteerConfirmed('desk-am', 'ada')], $stage->events());

        $quiet = new Rosters($stage->rosterRepository, $stage->clock, new Conflicts(), new FairAllocator());
        $quiet->plan(Stage::desk());
        self::assertSame(PlaceStatus::Confirmed, $quiet->signUp('desk-am', 'ada', [])->statusOf('ada'));
    }

    public function testTheHandlerAllocatesAndLogsWhatItCannot(): void
    {
        $stage = new Stage();
        $stage->rosters->plan(Stage::cloakroom());
        $stage->rosters->plan(Stage::desk());
        $stage->rosters->expressInterest('cloakroom', 'ada', []);

        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $lines = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $shift = $context['shift'] ?? null;
                $reason = $context['reason'] ?? null;
                $this->lines[] = (\is_string($level) ? $level : '').' '.strtr((string) $message, ['{shift}' => \is_string($shift) ? $shift : '', '{reason}' => \is_string($reason) ? $reason : '']);
            }
        };
        $handler = new AllocateInterestHandler($stage->rosters, $logger);

        $handler(new AllocateInterest('cloakroom'));
        self::assertSame(PlaceStatus::Confirmed, $stage->rosters->find('cloakroom')?->statusOf('ada'));
        $handler(new AllocateInterest('desk-am'));
        $handler(new AllocateInterest('missing'));
        self::assertSame([
            'notice The places of shift desk-am were not allocated: shift.wrong_mode.',
            'notice The places of shift missing were not allocated: shifts.unknown_shift.',
        ], $logger->lines);

        new AllocateInterestHandler($stage->rosters)(new AllocateInterest('missing'));

        $busy = new AllocateInterestHandler(new Rosters(new RacingRosterRepository($stage->rosterRepository, 3), $stage->clock, new Conflicts(), new FairAllocator()));
        $this->assertRequestRefused(RequestRefused::BUSY, static function () use ($busy): Roster {
            $busy(new AllocateInterest('cloakroom'));

            return Roster::open(Stage::desk());
        });
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

    /**
     * @param Closure(): mixed $operation
     */
    private function assertShiftRefused(string $reason, Closure $operation): void
    {
        try {
            $operation();
            self::fail('The operation is refused.');
        } catch (ShiftRefused $shiftRefused) {
            self::assertSame($reason, $shiftRefused->reason);
        }
    }
}
