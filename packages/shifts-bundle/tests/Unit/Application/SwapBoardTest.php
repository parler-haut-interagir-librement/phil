<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Unit\Application;

use Closure;
use Phil\Shifts\Event\PlaceTransferred;
use Phil\Shifts\Event\SwapOffered;
use Phil\Shifts\Event\SwapTaken;
use Phil\Shifts\Exception\ShiftRefused;
use Phil\Shifts\Roster\Conflicts;
use Phil\Shifts\Roster\PlaceStatus;
use Phil\Shifts\Swap\SwapOffer;
use Phil\Shifts\Swap\SwapStatus;
use Phil\ShiftsBundle\Application\Exception\RequestRefused;
use Phil\ShiftsBundle\Application\Exception\StaleWrite;
use Phil\ShiftsBundle\Application\SwapBoard;
use Phil\ShiftsBundle\Application\View\BoardEntry;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalTransaction;
use Phil\ShiftsBundle\Tests\Fixtures\RacingRosterRepository;
use Phil\ShiftsBundle\Tests\Fixtures\RacingSwapRepository;
use Phil\ShiftsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SwapBoard::class)]
#[CoversClass(BoardEntry::class)]
#[CoversClass(StaleWrite::class)]
final class SwapBoardTest extends TestCase
{
    public function testAPlacePassesAtOnce(): void
    {
        $stage = $this->stage();
        $offer = $stage->swaps->offer('desk-am', 'ada', 'Sick');

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $offer->id);
        self::assertEquals([new SwapOffered($offer->id, 'desk-am', 'ada')], $stage->events());
        self::assertSame(['desk-am'], array_map(static fn (BoardEntry $entry): string => $entry->roster->shift->id, $stage->swaps->board()));
        self::assertSame($offer->id, $stage->swaps->board()[0]->offer->id);

        $stage->forget();
        $taken = $stage->swaps->take($offer->id, 'cy', []);
        self::assertSame(SwapStatus::Completed, $taken->status);
        self::assertSame(SwapStatus::Completed, $stage->swaps->find($offer->id)?->status);
        self::assertSame(PlaceStatus::Confirmed, $stage->rosters->find('desk-am')?->statusOf('cy'));
        self::assertNull($stage->rosters->find('desk-am')?->statusOf('ada'));
        self::assertEquals([new PlaceTransferred('desk-am', 'cy', 'ada')], $stage->events());
        self::assertSame([], $stage->swaps->board());
        self::assertNull($stage->swaps->find('missing'));
    }

    public function testAPlaceWaitsForACoordinator(): void
    {
        $stage = $this->stage(approval: true);
        $offer = $stage->swaps->offer('desk-am', 'ada', '');
        $stage->forget();

        $taken = $stage->swaps->take($offer->id, 'cy', []);
        self::assertSame(SwapStatus::Pending, $taken->status);
        self::assertEquals([new SwapTaken($offer->id, 'desk-am', 'cy')], $stage->events());
        self::assertSame(PlaceStatus::Confirmed, $stage->rosters->find('desk-am')?->statusOf('ada'));
        self::assertSame(SwapStatus::Pending, $stage->swaps->board()[0]->offer->status);

        $refused = $stage->swaps->refuse($offer->id, 'grace');
        self::assertSame(SwapStatus::Open, $refused->status);
        self::assertCount(1, $stage->events());

        $stage->swaps->take($offer->id, 'dee', []);
        $stage->forget();

        $approved = $stage->swaps->approve($offer->id, 'grace');
        self::assertSame('grace', $approved->decidedBy);
        self::assertSame(PlaceStatus::Confirmed, $stage->rosters->find('desk-am')?->statusOf('dee'));
        self::assertEquals([new PlaceTransferred('desk-am', 'dee', 'ada')], $stage->events());
    }

    public function testTheVolunteerCancelsAndTheBoardHidesWhatCannotBeTaken(): void
    {
        $stage = $this->stage();
        $cancelled = $stage->swaps->offer('desk-am', 'ada', '');
        $stage->swaps->cancel($cancelled->id, 'ada');
        self::assertSame(SwapStatus::Cancelled, $stage->swaps->find($cancelled->id)?->status);

        $stale = $stage->swaps->offer('desk-am', 'bob', '');
        $stage->rosters->withdraw('desk-am', 'bob');
        self::assertSame([], $stage->swaps->board());
        $this->assertShiftRefused(ShiftRefused::SWAP_STALE, static fn (): SwapOffer => $stage->swaps->take($stale->id, 'dee', []));

        $closed = $stage->swaps->offer('desk-am', 'ada', '');
        self::assertCount(1, $stage->swaps->board());
        $stage->clock->modify('2026-11-28 09:00:00 UTC');
        self::assertSame([], $stage->swaps->board());
        $this->assertShiftRefused(ShiftRefused::CLOSED, static fn (): SwapOffer => $stage->swaps->take($closed->id, 'dee', []));
    }

    public function testTheRefusals(): void
    {
        $stage = $this->stage();
        $this->assertRequestRefused(RequestRefused::UNKNOWN_SHIFT, static fn (): SwapOffer => $stage->swaps->offer('missing', 'ada', ''));
        $this->assertRequestRefused(RequestRefused::UNKNOWN_OFFER, static fn (): SwapOffer => $stage->swaps->take('missing', 'ada', []));
        $this->assertShiftRefused(ShiftRefused::NOT_CONFIRMED, static fn (): SwapOffer => $stage->swaps->offer('desk-am', 'cy', ''));

        $offer = $stage->swaps->offer('desk-am', 'ada', '');
        $this->assertShiftRefused(ShiftRefused::SWAP_OWN_OFFER, static fn (): SwapOffer => $stage->swaps->take($offer->id, 'ada', []));
        $this->assertShiftRefused(ShiftRefused::SWAP_NOT_YOURS, static fn (): SwapOffer => $stage->swaps->cancel($offer->id, 'cy'));
        $this->assertShiftRefused(ShiftRefused::SWAP_NOT_PENDING, static fn (): SwapOffer => $stage->swaps->approve($offer->id, 'grace'));

        $stage->rosters->plan(Stage::desk('noon', '11:00:00', '13:00:00'));
        $stage->rosters->signUp('noon', 'dee', []);
        $this->assertShiftRefused(ShiftRefused::CONFLICT, static fn (): SwapOffer => $stage->swaps->take($offer->id, 'dee', []));
        $stage->rosters->plan(Stage::desk('reserved', '11:00:00', '13:00:00', capacity: 1));
        $stage->rosters->signUp('reserved', 'eve', []);
        $stage->rosters->signUp('reserved', 'fay', []);
        self::assertSame(SwapStatus::Completed, $stage->swaps->take($offer->id, 'fay', [])->status);

        $orphan = new SwapOffer('orphan', 'gone', 'ada', Stage::at('06:00:00'));
        $stage->swapRepository->add($orphan);
        $this->assertRequestRefused(RequestRefused::UNKNOWN_SHIFT, static fn (): SwapOffer => $stage->swaps->take('orphan', 'cy', []));
        self::assertSame([], array_filter($stage->swaps->board(), static fn (BoardEntry $entry): bool => 'orphan' === $entry->offer->id));
    }

    public function testADecisionIsWrittenAgainThenRefusedAsBusy(): void
    {
        $stage = $this->stage();
        $offer = $stage->swaps->offer('desk-am', 'ada', '');
        $board = static fn (int $losses): SwapBoard => new SwapBoard(new RacingSwapRepository($stage->swapRepository, $losses), $stage->rosterRepository, $stage->rosters, new DbalTransaction($stage->connection), $stage->clock, new Conflicts());

        $this->assertRequestRefused(RequestRefused::BUSY, static fn (): SwapOffer => $board(3)->take($offer->id, 'cy', []));
        self::assertSame(PlaceStatus::Confirmed, $stage->rosters->find('desk-am')?->statusOf('ada'));
        self::assertSame(SwapStatus::Open, $stage->swaps->find($offer->id)?->status);

        self::assertSame(SwapStatus::Completed, $board(2)->take($offer->id, 'cy', [])->status);
        self::assertSame(PlaceStatus::Confirmed, $stage->rosters->find('desk-am')?->statusOf('cy'));
    }

    public function testARosterChangedMeanwhileRollsTheOfferBack(): void
    {
        $stage = $this->stage();
        $offer = $stage->swaps->offer('desk-am', 'ada', '');
        $racing = new RacingRosterRepository($stage->rosterRepository, 3);
        $board = new SwapBoard($stage->swapRepository, $racing, $stage->rosters, new DbalTransaction($stage->connection), $stage->clock, new Conflicts());

        $this->assertRequestRefused(RequestRefused::BUSY, static fn (): SwapOffer => $board->take($offer->id, 'cy', []));
        self::assertSame(SwapStatus::Open, $stage->swaps->find($offer->id)?->status);
        self::assertSame(1, $stage->swapRepository->find($offer->id)?->revision);
    }

    public function testTheBoardListsEveryEntryAndReadsEachRosterOnce(): void
    {
        $stage = $this->stage();
        $stage->swaps->offer('desk-am', 'ada', '');
        $stage->swaps->offer('desk-am', 'bob', '');

        $racing = new RacingRosterRepository($stage->rosterRepository, 0);
        $swaps = new RacingSwapRepository($stage->swapRepository, 0);
        $board = new SwapBoard($swaps, $racing, $stage->rosters, new DbalTransaction($stage->connection), $stage->clock, new Conflicts());

        self::assertCount(2, $board->board());
        self::assertSame(1, $racing->reads);
        self::assertSame(200, $swaps->limit);
        $board->board(3);
        self::assertSame(3, $swaps->limit);
    }

    public function testWithoutADispatcherTheBoardStillWorks(): void
    {
        $stage = $this->stage(approval: true);
        $board = new SwapBoard($stage->swapRepository, $stage->rosterRepository, $stage->rosters, new DbalTransaction($stage->connection), $stage->clock, new Conflicts());

        $offer = $board->offer('desk-am', 'ada', '');
        self::assertSame(SwapStatus::Pending, $board->take($offer->id, 'cy', [])->status);
    }

    private function stage(bool $approval = false): Stage
    {
        $stage = new Stage();
        $stage->rosters->plan(Stage::desk(approval: $approval));
        $stage->rosters->signUp('desk-am', 'ada', []);
        $stage->rosters->signUp('desk-am', 'bob', []);
        $stage->rosters->signUp('desk-am', 'cy', []);
        $stage->forget();

        return $stage;
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
