<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Unit\Application;

use Closure;
use DateTimeImmutable;
use Phil\Shifts\Exception\ShiftRefused;
use Phil\Shifts\Roster\Place;
use Phil\Shifts\Roster\PlaceStatus;
use Phil\Shifts\Swap\SwapStatus;
use Phil\ShiftsBundle\Application\Absences;
use Phil\ShiftsBundle\Application\Exception\RequestRefused;
use Phil\ShiftsBundle\Application\Rosters;
use Phil\ShiftsBundle\Application\SwapBoard;
use Phil\ShiftsBundle\Domain\Model\Absence;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalAbsenceRepository;
use Phil\ShiftsBundle\Infrastructure\Doctrine\Json;
use Phil\ShiftsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Absences::class)]
#[CoversClass(Rosters::class)]
#[CoversClass(SwapBoard::class)]
#[CoversClass(DbalAbsenceRepository::class)]
#[CoversClass(Json::class)]
final class AbsencesTest extends TestCase
{
    public function testACoordinatorRecordsAnAbsenceOnceTheShiftStarted(): void
    {
        $stage = new Stage();
        $roster = $stage->rosters->plan(Stage::desk(capacity: 1));
        $stage->rosters->signUp('desk-am', 'ada', []);
        $stage->rosters->signUp('desk-am', 'bob', []);

        self::assertFalse($stage->absences->hasStarted($roster));
        $this->assertRequestRefused(RequestRefused::NOT_STARTED, static fn (): mixed => $stage->absences->record('desk-am', 'ada', 'grace'));
        $this->assertRequestRefused(RequestRefused::UNKNOWN_SHIFT, static fn (): mixed => $stage->absences->record('missing', 'ada', 'grace'));

        $stage->clock->modify('2026-11-28 09:00:00 UTC');
        self::assertTrue($stage->absences->hasStarted($roster));
        $this->assertShiftRefused(ShiftRefused::NOT_ON_ROSTER, static fn (): mixed => $stage->absences->record('desk-am', 'bob', 'grace'));
        $this->assertShiftRefused(ShiftRefused::NOT_ON_ROSTER, static fn (): mixed => $stage->absences->record('desk-am', 'nobody', 'grace'));

        self::assertTrue($stage->absences->record('desk-am', 'ada', 'grace'));
        $stage->clock->modify('+10 minutes');
        self::assertFalse($stage->absences->record('desk-am', 'ada', 'olga'));
        self::assertSame(1, $stage->absences->count('ada'));
        self::assertSame(0, $stage->absences->count('bob'));
        self::assertSame(['ada'], $stage->absences->absentFrom('desk-am'));
        self::assertSame([], $stage->absences->absentFrom('other'));
        self::assertEquals([new Absence('desk-am', 'ada', new DateTimeImmutable('2026-11-28T09:00:00Z'), 'grace')], $stage->absences->ofVolunteer('ada'));
        // Without a threshold, the absences block nothing.
        self::assertFalse($stage->absences->isBlocked('ada'));

        self::assertTrue($stage->absences->forgive('desk-am', 'ada'));
        self::assertFalse($stage->absences->forgive('desk-am', 'ada'));
        self::assertSame(0, $stage->absences->count('ada'));
        self::assertSame([], $stage->absences->ofVolunteer('ada'));
    }

    public function testPastTheThresholdAVolunteerNoLongerJoinsByThemselvesUntilTheBlockIsLifted(): void
    {
        $stage = new Stage(absenceThreshold: 2);
        $stage->rosters->plan(Stage::desk('early', '06:00:00', '07:00:00'));
        $stage->rosters->plan(Stage::desk('morning', '07:00:00', '08:00:00'));
        $stage->rosters->plan(Stage::desk('later', '20:00:00', '21:00:00'));
        $stage->rosters->plan(Stage::desk('evening', '21:00:00', '22:00:00'));
        $stage->rosters->plan(Stage::cloakroom(capacity: 3));

        foreach (['early', 'morning'] as $shift) {
            $stage->rosters->signUp($shift, 'ada', []);
        }

        $stage->rosters->signUp('later', 'bob', []);
        $offer = $stage->swaps->offer('later', 'bob', '');
        $stage->rosters->expressInterest('cloakroom', 'ada', []);
        $stage->rosters->expressInterest('cloakroom', 'cy', []);
        $stage->clock->modify('2026-11-28 07:30:00 UTC');

        $stage->absences->record('early', 'ada', 'grace');
        self::assertFalse($stage->absences->isBlocked('ada'));
        $stage->absences->record('morning', 'ada', 'grace');
        self::assertSame(2, $stage->absences->count('ada'));
        self::assertTrue($stage->absences->isBlocked('ada'));
        self::assertFalse($stage->absences->isBlocked('cy'));

        $this->assertRequestRefused(RequestRefused::ABSENCES, static fn (): mixed => $stage->rosters->signUp('evening', 'ada', []));
        $this->assertRequestRefused(RequestRefused::ABSENCES, static fn (): mixed => $stage->rosters->expressInterest('cloakroom', 'ada', []));
        $this->assertRequestRefused(RequestRefused::ABSENCES, static fn (): mixed => $stage->swaps->take($offer->id, 'ada', []));
        $this->assertRequestRefused(RequestRefused::ABSENCES, static function () use ($stage): void {
            $stage->absences->assertMayJoinFreely('ada');
        });
        $stage->absences->assertMayJoinFreely('cy');

        $allocated = $stage->rosters->allocate('cloakroom');
        self::assertSame(['cy'], array_map(static fn (Place $place): string => $place->volunteer, $allocated->confirmed()));
        self::assertSame(['ada'], array_map(static fn (Place $place): string => $place->volunteer, $allocated->interested()));

        // A coordinator may still give them a place.
        self::assertSame('grace', $stage->rosters->assign('evening', 'ada', 'grace')->confirmed()[0]->assignedBy);
        $stage->rosters->withdraw('evening', 'ada');

        $stage->clock->modify('+1 hour');
        self::assertSame(2, $stage->absences->lift('ada', 'olga'));
        self::assertSame(0, $stage->absences->lift('ada', 'olga'));
        self::assertSame(0, $stage->absences->count('ada'));
        self::assertFalse($stage->absences->isBlocked('ada'));
        $lifted = new DateTimeImmutable('2026-11-28T08:30:00Z');
        self::assertEquals([
            new Absence('early', 'ada', new DateTimeImmutable('2026-11-28T07:30:00Z'), 'grace', $lifted, 'olga'),
            new Absence('morning', 'ada', new DateTimeImmutable('2026-11-28T07:30:00Z'), 'grace', $lifted, 'olga'),
        ], $stage->absences->ofVolunteer('ada'));
        self::assertSame(PlaceStatus::Confirmed, $stage->rosters->signUp('evening', 'ada', [])->statusOf('ada'));
        self::assertSame(SwapStatus::Completed, $stage->swaps->take($offer->id, 'ada', [])->status);
    }

    public function testOnlyTheAbsencesOfTheWindowCount(): void
    {
        $stage = new Stage();
        $stage->rosters->plan(Stage::desk());
        $stage->rosters->signUp('desk-am', 'ada', []);
        $stage->clock->modify('2026-11-28 09:00:00 UTC');
        $stage->absences->record('desk-am', 'ada', 'grace');

        $absences = new Absences($stage->absenceRepository, $stage->rosterRepository, $stage->clock, 1, 30);

        self::assertTrue($absences->isBlocked('ada'));
        $stage->clock->modify('2026-12-28 09:00:00 UTC');
        self::assertSame(1, $absences->count('ada'));
        $stage->clock->modify('+1 second');
        self::assertSame(0, $absences->count('ada'));
        self::assertFalse($absences->isBlocked('ada'));
        self::assertSame(1, $stage->absences->count('ada'));
        $stage->clock->modify('2027-11-28 09:00:00 UTC');
        self::assertSame(1, $stage->absences->count('ada'));
        $stage->clock->modify('+1 second');
        self::assertSame(0, $stage->absences->count('ada'));
        self::assertCount(1, $stage->absences->ofVolunteer('ada'));
    }

    public function testTheAbsencesOfAVolunteerAreErasedAndBounded(): void
    {
        $stage = new Stage();
        $repository = $stage->absenceRepository;
        $at = new DateTimeImmutable('2026-11-28T09:00:00Z');

        for ($shift = 0; $shift < 502; ++$shift) {
            self::assertTrue($repository->add(new Absence(\sprintf('s%03d', $shift), 'ada', $at->modify(\sprintf('+%d minutes', $shift)), 'grace')));
        }

        self::assertTrue($repository->add(new Absence('s000', 'bob', $at, 'grace')));
        $latest = $repository->ofVolunteer('ada');
        self::assertCount(500, $latest);
        self::assertSame('s501', $latest[0]->shiftId);
        self::assertSame('s002', $latest[499]->shiftId);
        self::assertSame(['ada', 'bob'], $repository->absentFrom('s000'));
        self::assertSame(502, $repository->countSince('ada', $at));
        self::assertSame(501, $repository->countSince('ada', $at->modify('+1 minute')));

        $repository->eraseVolunteer('ada');
        self::assertSame([], $repository->ofVolunteer('ada'));
        self::assertSame(['bob'], $repository->absentFrom('s000'));
        self::assertSame(1, $repository->countSince('bob', $at));
    }

    /**
     * @param Closure(): mixed|Closure(): void $operation
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
