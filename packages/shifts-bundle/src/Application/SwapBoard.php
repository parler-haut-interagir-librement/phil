<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Application;

use Closure;
use Phil\Shifts\Event\SwapOffered;
use Phil\Shifts\Event\SwapTaken;
use Phil\Shifts\Exception\ShiftRefused;
use Phil\Shifts\Roster\Conflicts;
use Phil\Shifts\Roster\PlaceStatus;
use Phil\Shifts\Roster\Roster;
use Phil\Shifts\Swap\SwapOffer;
use Phil\Shifts\Swap\SwapStatus;
use Phil\ShiftsBundle\Application\Contract\Transaction;
use Phil\ShiftsBundle\Application\Exception\RequestRefused;
use Phil\ShiftsBundle\Application\Exception\StaleWrite;
use Phil\ShiftsBundle\Application\View\BoardEntry;
use Phil\ShiftsBundle\Domain\Repository\RosterRepository;
use Phil\ShiftsBundle\Domain\Repository\SwapRepository;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The swap board: a volunteer offers their place, another one takes it, a coordinator approves or refuses when the
 * shift asks for it. A completed offer and the roster whose place it passed are written together, each over the
 * revision read; the volunteer who takes a place never gets a fixed shift that overlaps one they hold.
 */
final readonly class SwapBoard
{
    private const int WRITES = 3;

    public function __construct(
        private SwapRepository $swaps,
        private RosterRepository $rosters,
        private Rosters $service,
        private Transaction $transaction,
        private ClockInterface $clock,
        private Conflicts $conflicts,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * A volunteer puts their place on the board.
     *
     * @throws ShiftRefused   when the volunteer holds no place of the open shift
     * @throws RequestRefused when the shift is unknown
     */
    public function offer(string $shiftId, string $volunteer, string $note): SwapOffer
    {
        $roster = ($this->rosters->find($shiftId) ?? throw new RequestRefused(RequestRefused::UNKNOWN_SHIFT))->roster;
        $offer = SwapOffer::offer(Uuid::v7()->toRfc4122(), $roster, $volunteer, $note, $this->clock->now());
        $this->swaps->add($offer);
        $this->events?->dispatch(new SwapOffered($offer->id, $offer->shiftId, $volunteer));

        return $offer;
    }

    /**
     * A volunteer takes an offer. A place that would overlap a fixed shift the volunteer
     * holds is refused with `shift.conflict`; a minor without the consent of a guardian, with
     * `shifts.guardian_consent`; a volunteer past the threshold of absences, with `shifts.absences`.
     *
     * @param list<string> $roles the roles of the volunteer who takes the place
     *
     * @throws ShiftRefused   when the offer or the roster refuses
     * @throws RequestRefused when the offer or its shift is unknown, or busy, or the volunteer may not join
     */
    public function take(string $offerId, string $volunteer, array $roles): SwapOffer
    {
        return $this->decide($offerId, function (SwapOffer $offer, Roster $roster) use ($volunteer, $roles): SwapOffer {
            $this->service->assertMayTake($roster, $volunteer);
            $held = [];

            foreach ($this->rosters->ofVolunteer($volunteer) as $other) {
                if (PlaceStatus::Confirmed === $other->statusOf($volunteer)) {
                    $held[] = $other->shift;
                }
            }

            if ([] !== $this->conflicts->of($roster->shift, $held)) {
                throw ShiftRefused::because(ShiftRefused::CONFLICT);
            }

            return $offer->take($roster, $volunteer, $roles, $this->clock->now());
        });
    }

    /**
     * A coordinator approves a taken offer.
     *
     * @throws ShiftRefused   when the offer or the roster refuses
     * @throws RequestRefused when the offer or its shift is unknown, or busy
     */
    public function approve(string $offerId, string $coordinator): SwapOffer
    {
        return $this->decide($offerId, fn (SwapOffer $offer, Roster $roster): SwapOffer => $offer->approve($roster, $coordinator, $this->clock->now()));
    }

    /**
     * A coordinator refuses a taken offer, which goes back on the board.
     *
     * @throws ShiftRefused   when the offer or the roster refuses
     * @throws RequestRefused when the offer or its shift is unknown, or busy
     */
    public function refuse(string $offerId, string $coordinator): SwapOffer
    {
        return $this->decide($offerId, fn (SwapOffer $offer): SwapOffer => $offer->refuse($coordinator, $this->clock->now()));
    }

    /**
     * The volunteer who made the offer cancels it.
     *
     * @throws ShiftRefused   when the offer or the roster refuses
     * @throws RequestRefused when the offer or its shift is unknown, or busy
     */
    public function cancel(string $offerId, string $volunteer): SwapOffer
    {
        return $this->decide($offerId, fn (SwapOffer $offer): SwapOffer => $offer->cancel($volunteer, $this->clock->now()));
    }

    /**
     * @phpstan-impure
     */
    public function find(string $offerId): ?SwapOffer
    {
        return $this->swaps->find($offerId)?->offer;
    }

    /**
     * The offers on the board with their shift: open or waiting, of shifts still open, whose volunteer still holds the
     * place.
     *
     * @return list<BoardEntry>
     *
     * @phpstan-impure
     */
    public function board(int $limit = 200): array
    {
        $now = $this->clock->now();
        $entries = [];
        $rosters = [];

        foreach ($this->swaps->active($limit) as $offer) {
            $roster = $rosters[$offer->shiftId] ??= $this->rosters->find($offer->shiftId)?->roster;

            if ($roster instanceof Roster && $roster->shift->isOpenAt($now) && !$offer->isStale($roster)) {
                $entries[] = new BoardEntry($offer, $roster);
            }
        }

        return $entries;
    }

    /**
     * Reads the offer and its roster, decides, and writes the offer — with the roster when the place passed —, three
     * times at most.
     *
     * @param Closure(SwapOffer, Roster): SwapOffer $decision
     *
     * @param-immediately-invoked-callable $decision
     *
     * @throws RequestRefused when the offer or its shift is unknown, or another request wrote each time first
     */
    private function decide(string $offerId, Closure $decision): SwapOffer
    {
        for ($write = 0; $write < self::WRITES; ++$write) {
            $stored = $this->swaps->find($offerId) ?? throw new RequestRefused(RequestRefused::UNKNOWN_OFFER);
            $roster = $this->rosters->find($stored->offer->shiftId) ?? throw new RequestRefused(RequestRefused::UNKNOWN_SHIFT);
            $offer = $decision($stored->offer, $roster->roster);
            $passed = SwapStatus::Completed === $offer->status ? $offer->apply($roster->roster, $this->clock->now()) : null;

            try {
                $this->transaction->run(function () use ($offer, $stored, $passed, $roster): void {
                    if (!$this->swaps->update($offer, $stored->revision) || ($passed instanceof Roster && !$this->rosters->update($passed, $roster->revision))) {
                        throw new StaleWrite();
                    }
                });
            } catch (StaleWrite) {
                continue;
            }

            if ($passed instanceof Roster) {
                $this->service->dispatch($passed);
            } elseif (null !== $offer->takenBy && SwapStatus::Pending === $offer->status && SwapStatus::Open === $stored->offer->status) {
                $this->events?->dispatch(new SwapTaken($offer->id, $offer->shiftId, $offer->takenBy));
            }

            return $offer;
        }

        throw new RequestRefused(RequestRefused::BUSY);
    }
}
