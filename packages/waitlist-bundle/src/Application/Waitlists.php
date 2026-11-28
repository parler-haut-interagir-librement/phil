<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Application;

use DateTimeImmutable;
use Phil\WaitlistBundle\Application\Contract\OfferPolicy;
use Phil\WaitlistBundle\Application\Exception\NotFound;
use Phil\WaitlistBundle\Application\Exception\RefusalReason;
use Phil\WaitlistBundle\Application\Exception\WaitlistRefused;
use Phil\WaitlistBundle\Domain\Contract\EntryRepository;
use Phil\WaitlistBundle\Domain\Contract\WaitlistRepository;
use Phil\WaitlistBundle\Domain\Event\EntryJoined;
use Phil\WaitlistBundle\Domain\Event\EntryLeft;
use Phil\WaitlistBundle\Domain\Event\OfferAccepted;
use Phil\WaitlistBundle\Domain\Event\OfferDeclined;
use Phil\WaitlistBundle\Domain\Event\OfferLapsed;
use Phil\WaitlistBundle\Domain\Event\PlaceOffered;
use Phil\WaitlistBundle\Domain\Event\WaitlistDrawn;
use Phil\WaitlistBundle\Domain\Exception\InvalidWaitlist;
use Phil\WaitlistBundle\Domain\Model\Entry;
use Phil\WaitlistBundle\Domain\Model\Waitlist;
use Phil\WaitlistBundle\Domain\Status\EntryStatus;
use Phil\WaitlistBundle\Domain\Status\WaitlistMode;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;
use UnexpectedValueException;

/**
 * The waiting lists and their entries. Each request that changes a list runs under the list's lock: it reads the
 * entries, decides, and saves, before another request on the same list reads anything. The events go out once the
 * change is saved, and carry identifiers only.
 *
 * The application tells a list when places free up; the list offers them to the entries its `OfferPolicy` picks, each
 * holding its offer until a deadline. An offer accepted is the application's to honour; one declined, lapsed or left
 * gives its places back, and they are offered to the next entries at once.
 */
final readonly class Waitlists
{
    /**
     * @param int $offerWindow how long an offer is held, in seconds, when a list is opened without its own
     * @param int $maxPlaces   the most places one entry asks for
     * @param int $batch       the most entries read at a time: waiting entries when places free up, offers when they lapse
     */
    public function __construct(
        private WaitlistRepository $waitlists,
        private EntryRepository $entries,
        private OfferPolicy $policy,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
        private int $offerWindow = 172_800,
        private int $maxPlaces = 10,
        private int $batch = 100,
    ) {
    }

    /**
     * The waiting list of a subject, opened at its first call; a later call gives the same list back, unchanged.
     *
     * @param int|null $offerWindow how long an offer is held, in seconds; the configured window when null
     *
     * @throws InvalidWaitlist when the subject is not a kind and an identifier, or the window out of bounds
     */
    public function open(string $subjectType, string $subjectId, WaitlistMode $mode = WaitlistMode::Queue, ?int $offerWindow = null): Waitlist
    {
        $existing = $this->waitlists->ofSubject($subjectType, $subjectId);

        if ($existing instanceof Waitlist) {
            return $existing;
        }

        $waitlist = Waitlist::open($subjectType, $subjectId, $mode, $offerWindow ?? $this->offerWindow, $this->clock->now());
        $this->waitlists->save($waitlist);

        return $waitlist;
    }

    /**
     * @throws NotFound when the list does not exist
     */
    public function waitlist(string $id): Waitlist
    {
        return $this->waitlists->get($id) ?? throw NotFound::waitlist($id);
    }

    /**
     * The waiting list of a subject, if it has one.
     */
    public function ofSubject(string $subjectType, string $subjectId): ?Waitlist
    {
        return $this->waitlists->ofSubject($subjectType, $subjectId);
    }

    /**
     * @throws NotFound when the entry does not exist
     */
    public function entry(string $id): Entry
    {
        return $this->entries->get($id) ?? throw NotFound::entry($id);
    }

    /**
     * A list's entries, of one status or all, by rank.
     *
     * @return list<Entry>
     *
     * @throws NotFound when the list does not exist
     */
    public function entries(string $waitlistId, ?EntryStatus $status = null, int $limit = 100): array
    {
        return $this->entries->ofWaitlist($this->waitlist($waitlistId), max(1, min($limit, $this->batch)), $status);
    }

    /**
     * Puts a participant on a list, behind those already in it. A participant already waiting or holding an offer in
     * the list gets that entry back, its rank unchanged. When the list holds places that fit, the entry may be offered
     * them at once.
     *
     * @throws NotFound                 when the list does not exist
     * @throws WaitlistRefused          when the list is closed or the places too many
     * @throws InvalidWaitlist          when the participant is not an identifier, or the places below one
     * @throws UnexpectedValueException when the offer policy picks an entry it was not given, or too many places
     */
    public function join(string $waitlistId, string $participant, int $places = 1): Entry
    {
        $waitlist = $this->waitlist($waitlistId);

        [$entry, $joined, $offered] = $this->waitlists->exclusively($waitlist, function () use ($waitlist, $participant, $places): array {
            $existing = $this->entries->live($waitlist, $participant);

            if ($existing instanceof Entry) {
                return [$existing, false, []];
            }

            if ($waitlist->isClosed()) {
                throw new WaitlistRefused(RefusalReason::Closed);
            }

            if ($places > $this->maxPlaces) {
                throw new WaitlistRefused(RefusalReason::TooManyPlaces, ['max' => $this->maxPlaces]);
            }

            $now = $this->clock->now();
            $entry = Entry::join($waitlist, $participant, $places, $now);
            $this->entries->save($entry);

            return [$entry, true, $this->offer($waitlist, $now)];
        });

        if ($joined) {
            $this->dispatch(new EntryJoined($entry->id, $waitlist->id));
        }

        $this->dispatchOffers($offered);

        return $entry;
    }

    /**
     * Where an entry stands now.
     *
     * @throws NotFound when the entry does not exist
     */
    public function standing(string $entryId): Standing
    {
        $entry = $this->entry($entryId);
        $waiting = EntryStatus::Waiting === $entry->status();

        return new Standing(
            $entry->id,
            $entry->status(),
            $waiting ? $this->entries->ahead($entry) + 1 : null,
            EntryStatus::Offered === $entry->status() ? $entry->offerExpiresAt() : null,
        );
    }

    /**
     * A list's places now.
     *
     * @throws NotFound when the list does not exist
     */
    public function count(string $waitlistId): ListCount
    {
        $waitlist = $this->waitlist($waitlistId);

        return new ListCount(
            $this->entries->places($waitlist, EntryStatus::Waiting),
            $this->entries->places($waitlist, EntryStatus::Offered),
            $waitlist->heldPlaces(),
        );
    }

    /**
     * Places freed in the subject: the list offers them to the entries its policy picks, and holds the rest.
     *
     * @return list<Entry> the entries offered places
     *
     * @throws NotFound                 when the list does not exist
     * @throws InvalidWaitlist          when the places are below one, or the list would hold too many
     * @throws UnexpectedValueException when the offer policy picks an entry it was not given, or too many places
     */
    public function release(string $waitlistId, int $places): array
    {
        $waitlist = $this->waitlist($waitlistId);

        $offered = $this->waitlists->exclusively($waitlist, function () use ($waitlist, $places): array {
            $waitlist->hold($places);
            $this->waitlists->save($waitlist);

            return $this->offer($waitlist, $this->clock->now());
        });

        $this->dispatchOffers($offered);

        return $offered;
    }

    /**
     * Takes back places the list holds and has not offered, such as when the subject loses capacity.
     *
     * @return int the places taken back: at most those held
     *
     * @throws NotFound        when the list does not exist
     * @throws InvalidWaitlist when the places are below one
     */
    public function reclaim(string $waitlistId, int $places): int
    {
        if ($places < 1) {
            throw InvalidWaitlist::because('At least one place is reclaimed.');
        }

        $waitlist = $this->waitlist($waitlistId);

        return $this->waitlists->exclusively($waitlist, function () use ($waitlist, $places): int {
            $taken = min($places, $waitlist->heldPlaces());

            if ($taken > 0) {
                $waitlist->take($taken);
                $this->waitlists->save($waitlist);
            }

            return $taken;
        });
    }

    /**
     * Accepts the offer an entry holds; the application then gives its participant the places.
     *
     * @throws NotFound        when the entry does not exist
     * @throws WaitlistRefused when the entry holds no offer, or its deadline has come
     */
    public function accept(string $entryId): Entry
    {
        $entry = $this->entry($entryId);
        $waitlist = $entry->waitlist();

        $this->waitlists->exclusively($waitlist, function () use ($entry): void {
            $this->entries->refresh($entry);
            $now = $this->clock->now();

            if (EntryStatus::Offered !== $entry->status()) {
                throw new WaitlistRefused(RefusalReason::NoOffer);
            }

            if ($entry->hasExpiredAt($now)) {
                throw new WaitlistRefused(RefusalReason::OfferExpired);
            }

            $entry->accept($now);
            $this->entries->save($entry);
        });

        $this->dispatch(new OfferAccepted($entry->id, $waitlist->id));

        return $entry;
    }

    /**
     * Declines the offer an entry holds; its places are offered to the next entries.
     *
     * @throws NotFound                 when the entry does not exist
     * @throws WaitlistRefused          when the entry holds no offer
     * @throws UnexpectedValueException when the offer policy picks an entry it was not given, or too many places
     */
    public function decline(string $entryId): Entry
    {
        $entry = $this->entry($entryId);
        $waitlist = $entry->waitlist();

        $offered = $this->waitlists->exclusively($waitlist, function () use ($entry, $waitlist): array {
            $this->entries->refresh($entry);

            if (EntryStatus::Offered !== $entry->status()) {
                throw new WaitlistRefused(RefusalReason::NoOffer);
            }

            $now = $this->clock->now();
            $entry->decline($now);

            return $this->giveBack($entry, $waitlist, $now);
        });

        $this->dispatch(new OfferDeclined($entry->id, $waitlist->id));
        $this->dispatchOffers($offered);

        return $entry;
    }

    /**
     * Takes an entry out of its list, waiting or holding an offer; the places of an offer go to the next entries.
     *
     * @throws NotFound                 when the entry does not exist
     * @throws InvalidWaitlist          when it has left the list already
     * @throws UnexpectedValueException when the offer policy picks an entry it was not given, or too many places
     */
    public function leave(string $entryId): Entry
    {
        $entry = $this->entry($entryId);
        $waitlist = $entry->waitlist();

        $offered = $this->waitlists->exclusively($waitlist, function () use ($entry, $waitlist): array {
            $this->entries->refresh($entry);
            $held = EntryStatus::Offered === $entry->status();
            $now = $this->clock->now();
            $entry->leave($now);

            if ($held) {
                return $this->giveBack($entry, $waitlist, $now);
            }

            $this->entries->save($entry);

            return [];
        });

        $this->dispatch(new EntryLeft($entry->id, $waitlist->id));
        $this->dispatchOffers($offered);

        return $entry;
    }

    /**
     * Lapses the offers whose deadline has come, list by list, and offers their places to the next entries. A
     * scheduler or the `phil:waitlist:lapse` command calls it.
     *
     * @return int the offers lapsed
     *
     * @throws UnexpectedValueException when the offer policy picks an entry it was not given, or too many places
     */
    public function lapseExpired(): int
    {
        $lapsedCount = 0;

        foreach ($this->entries->waitlistsWithExpiredOffers($this->clock->now(), $this->batch) as $waitlistId) {
            $waitlist = $this->waitlists->get($waitlistId);

            if (!$waitlist instanceof Waitlist) {
                continue;
            }

            [$lapsed, $offered] = $this->waitlists->exclusively($waitlist, function () use ($waitlist): array {
                $now = $this->clock->now();
                $lapsed = [];

                foreach ($this->entries->expired($waitlist, $now, $this->batch) as $entry) {
                    $entry->lapse($now);
                    $this->entries->save($entry);
                    $waitlist->hold($entry->places());
                    $lapsed[] = $entry;
                }

                $this->waitlists->save($waitlist);

                return [$lapsed, $this->offer($waitlist, $now)];
            });

            foreach ($lapsed as $entry) {
                $this->dispatch(new OfferLapsed($entry->id, $waitlist->id));
            }

            $this->dispatchOffers($offered);
            $lapsedCount += \count($lapsed);
        }

        return $lapsedCount;
    }

    /**
     * Makes the draw of a list in draw mode: its waiting entries are ranked by the seed, then the places it holds are
     * offered. The seed is published with the list, so that anyone can rank the entries again.
     *
     * @param string|null $seed the seed to publish; a random UUID in base 58, 22 characters, when null
     *
     * @throws NotFound                 when the list does not exist
     * @throws InvalidWaitlist          when the list is not a draw, is drawn already, or the seed is not one
     * @throws UnexpectedValueException when the offer policy picks an entry it was not given, or too many places
     */
    public function draw(string $waitlistId, ?string $seed = null): Waitlist
    {
        $waitlist = $this->waitlist($waitlistId);
        $seed ??= Uuid::v4()->toBase58();

        $offered = $this->waitlists->exclusively($waitlist, function () use ($waitlist, $seed): array {
            $now = $this->clock->now();
            $waitlist->draw($seed, $now);

            do {
                $queued = $this->entries->queued($waitlist, $this->batch);

                foreach ($queued as $entry) {
                    $entry->rankBy($seed);
                    $this->entries->save($entry);
                }
            } while ([] !== $queued);

            $this->waitlists->save($waitlist);

            return $this->offer($waitlist, $now);
        });

        $this->dispatch(new WaitlistDrawn($waitlist->id));
        $this->dispatchOffers($offered);

        return $waitlist;
    }

    /**
     * Another offer window for the offers the list makes from now on.
     *
     * @throws NotFound        when the list does not exist
     * @throws InvalidWaitlist when the window is out of bounds
     */
    public function changeOfferWindow(string $waitlistId, int $seconds): Waitlist
    {
        $waitlist = $this->waitlist($waitlistId);

        $this->waitlists->exclusively($waitlist, function () use ($waitlist, $seconds): void {
            $waitlist->changeOfferWindow($seconds);
            $this->waitlists->save($waitlist);
        });

        return $waitlist;
    }

    /**
     * Takes no more entries; those in the list keep their turn and their offers.
     *
     * @throws NotFound        when the list does not exist
     * @throws InvalidWaitlist when it is closed already
     */
    public function close(string $waitlistId): Waitlist
    {
        $waitlist = $this->waitlist($waitlistId);

        $this->waitlists->exclusively($waitlist, function () use ($waitlist): void {
            $waitlist->close($this->clock->now());
            $this->waitlists->save($waitlist);
        });

        return $waitlist;
    }

    /**
     * @throws NotFound        when the list does not exist
     * @throws InvalidWaitlist when it is open
     */
    public function reopen(string $waitlistId): Waitlist
    {
        $waitlist = $this->waitlist($waitlistId);

        $this->waitlists->exclusively($waitlist, function () use ($waitlist): void {
            $waitlist->reopen();
            $this->waitlists->save($waitlist);
        });

        return $waitlist;
    }

    /**
     * Saves an entry that gave back the places of its offer, and offers them to the next entries.
     *
     * @return list<Entry> the entries offered places
     */
    private function giveBack(Entry $entry, Waitlist $waitlist, DateTimeImmutable $now): array
    {
        $this->entries->save($entry);
        $waitlist->hold($entry->places());
        $this->waitlists->save($waitlist);

        return $this->offer($waitlist, $now);
    }

    /**
     * Offers the places the list holds to the waiting entries the policy picks.
     *
     * @return list<Entry> the entries offered places
     *
     * @throws UnexpectedValueException when the policy's pick is wrong
     */
    private function offer(Waitlist $waitlist, DateTimeImmutable $now): array
    {
        $held = $waitlist->heldPlaces();

        if ($held < 1 || !$waitlist->isOffering()) {
            return [];
        }

        $waiting = $this->entries->waiting($waitlist, $this->batch);

        if ([] === $waiting) {
            return [];
        }

        $picked = $this->policy->pick($held, $waiting);
        $this->assertPick($picked, $waiting, $held);
        $deadline = $waitlist->offerDeadline($now);

        foreach ($picked as $entry) {
            $entry->offer($now, $deadline);
            $waitlist->take($entry->places());
            $this->entries->save($entry);
        }

        if ([] !== $picked) {
            $this->waitlists->save($waitlist);
        }

        return $picked;
    }

    /**
     * @param list<Entry> $picked
     * @param list<Entry> $waiting
     *
     * @throws UnexpectedValueException when an entry picked was not given, is picked twice, or the places run out
     */
    private function assertPick(array $picked, array $waiting, int $held): void
    {
        $given = [];

        foreach ($waiting as $entry) {
            $given[$entry->id] = $entry;
        }

        foreach ($picked as $entry) {
            if (!isset($given[$entry->id])) {
                throw new UnexpectedValueException(\sprintf('The offer policy picked "%s", which is not waiting or picked twice.', $entry->id));
            }

            unset($given[$entry->id]);
            $held -= $entry->places();
        }

        if ($held < 0) {
            throw new UnexpectedValueException('The offer policy picked more places than are held.');
        }
    }

    /**
     * @param list<Entry> $offered
     */
    private function dispatchOffers(array $offered): void
    {
        foreach ($offered as $entry) {
            $this->dispatch(new PlaceOffered($entry->id, $entry->waitlist()->id));
        }
    }

    private function dispatch(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
