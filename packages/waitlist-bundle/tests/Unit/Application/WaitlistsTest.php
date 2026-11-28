<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Tests\Unit\Application;

use Phil\WaitlistBundle\Application\Contract\OfferPolicy;
use Phil\WaitlistBundle\Application\Exception\NotFound;
use Phil\WaitlistBundle\Application\Exception\RefusalReason;
use Phil\WaitlistBundle\Application\Exception\WaitlistRefused;
use Phil\WaitlistBundle\Application\ListCount;
use Phil\WaitlistBundle\Application\Policy\FirstInLine;
use Phil\WaitlistBundle\Application\Policy\FirstThatFits;
use Phil\WaitlistBundle\Application\Standing;
use Phil\WaitlistBundle\Application\Waitlists;
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
use Phil\WaitlistBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\WaitlistBundle\Tests\Unit\Fixture\InMemoryEntries;
use Phil\WaitlistBundle\Tests\Unit\Fixture\InMemoryWaitlists;
use Phil\WaitlistBundle\Tests\Unit\Fixture\RecordingEvents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

#[CoversClass(Waitlists::class)]
#[CoversClass(Standing::class)]
#[CoversClass(ListCount::class)]
#[CoversClass(NotFound::class)]
#[CoversClass(WaitlistRefused::class)]
#[CoversClass(RefusalReason::class)]
#[CoversClass(EntryJoined::class)]
#[CoversClass(PlaceOffered::class)]
#[CoversClass(OfferAccepted::class)]
#[CoversClass(OfferDeclined::class)]
#[CoversClass(OfferLapsed::class)]
#[CoversClass(EntryLeft::class)]
#[CoversClass(WaitlistDrawn::class)]
final class WaitlistsTest extends TestCase
{
    private FrozenClock $clock;

    private InMemoryWaitlists $lists;

    private InMemoryEntries $entries;

    private RecordingEvents $events;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock();
        $this->lists = new InMemoryWaitlists();
        $this->entries = new InMemoryEntries($this->lists);
        $this->events = new RecordingEvents();
    }

    private function service(?OfferPolicy $policy = null, int $maxPlaces = 4, int $batch = 100, int $window = 3_600): Waitlists
    {
        return new Waitlists($this->lists, $this->entries, $policy ?? new FirstInLine(), $this->clock, $this->events, $window, $maxPlaces, $batch);
    }

    private function join(Waitlists $service, Waitlist $waitlist, string $participant, int $places = 1): Entry
    {
        $entry = $service->join($waitlist->id, $participant, $places);
        $this->clock->move('+1 second');

        return $entry;
    }

    public function testAListIsOpenedOncePerSubject(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');

        self::assertSame($waitlist, $service->open('session', 'S-1', WaitlistMode::Draw, 60));
        self::assertSame([WaitlistMode::Queue, 3_600], [$waitlist->mode(), $waitlist->offerWindow()]);
        self::assertSame($waitlist, $service->ofSubject('session', 'S-1'));
        self::assertNull($service->ofSubject('session', 'S-2'));
        self::assertSame($waitlist, $service->waitlist($waitlist->id));
        self::assertSame([$waitlist->id.' unlocked'], $this->lists->saved);

        $draw = $service->open('session', 'S-2', WaitlistMode::Draw, 600);
        self::assertSame([WaitlistMode::Draw, 600], [$draw->mode(), $draw->offerWindow()]);
    }

    public function testTheConfiguredWindowIsTheDefault(): void
    {
        self::assertSame(172_800, new Waitlists($this->lists, $this->entries, new FirstInLine(), $this->clock)->open('session', 'S-1')->offerWindow());
    }

    public function testAMissingListOrEntryIsNotFound(): void
    {
        $service = $this->service();

        foreach ([
            static fn (): mixed => $service->waitlist('missing'),
            static fn (): mixed => $service->entry('missing'),
            static fn (): mixed => $service->join('missing', 'account-1'),
            static fn (): mixed => $service->standing('missing'),
            static fn (): mixed => $service->accept('missing'),
        ] as $call) {
            try {
                $call();
                self::fail('Nothing by that identifier.');
            } catch (NotFound $notFound) {
                self::assertStringStartsWith('No waiting list', $notFound->getMessage());
            }
        }

        $long = 'a'.str_repeat('é', 79);

        foreach ([
            ['No waiting list "a'.str_repeat('é', 63).'".', static fn (): mixed => $service->waitlist($long)],
            ['No waiting list entry "a'.str_repeat('é', 63).'".', static fn (): mixed => $service->entry($long)],
        ] as [$message, $call]) {
            try {
                $call();
                self::fail('Nothing by that identifier.');
            } catch (NotFound $notFound) {
                self::assertSame($message, $notFound->getMessage());
            }
        }
    }

    public function testParticipantsWaitInTheOrderTheyJoin(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');
        $first = $this->join($service, $waitlist, 'account-1', 2);
        $second = $this->join($service, $waitlist, 'account-2');

        self::assertEquals(new Standing($first->id, EntryStatus::Waiting, 1, null), $service->standing($first->id));
        self::assertSame(2, $service->standing($second->id)->position);
        self::assertEquals(new ListCount(3, 0, 0), $service->count($waitlist->id));
        self::assertSame([$first, $second], $service->entries($waitlist->id));
        self::assertSame(['EntryJoined '.$first->id, 'EntryJoined '.$second->id], $this->events->names());
        self::assertSame([$first->id.' locked', $second->id.' locked'], $this->entries->saved);
        self::assertSame([$waitlist->id, $waitlist->id], $this->lists->locked);
    }

    public function testASecondJoinGivesTheSameEntryBack(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');
        $entry = $this->join($service, $waitlist, 'account-1');
        $this->join($service, $waitlist, 'account-2');

        self::assertSame($entry, $service->join($waitlist->id, 'account-1', 3));
        self::assertSame(1, $service->standing($entry->id)->position);
        self::assertSame(1, $entry->places());
        self::assertCount(2, $this->events->events);
    }

    public function testAParticipantOutOfTheListJoinsAgainAtTheEnd(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');
        $entry = $this->join($service, $waitlist, 'account-1');
        $this->join($service, $waitlist, 'account-2');
        $service->leave($entry->id);

        $again = $service->join($waitlist->id, 'account-1');

        self::assertNotSame($entry->id, $again->id);
        self::assertSame(2, $service->standing($again->id)->position);
    }

    public function testAClosedListTakesNoEntryButKeepsThoseIn(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');
        $entry = $this->join($service, $waitlist, 'account-1');
        self::assertSame($waitlist, $service->close($waitlist->id));

        self::assertSame($entry, $service->join($waitlist->id, 'account-1'));

        try {
            $service->join($waitlist->id, 'account-2');
            self::fail('A closed list takes no entry.');
        } catch (WaitlistRefused $waitlistRefused) {
            self::assertSame([RefusalReason::Closed, []], [$waitlistRefused->reason, $waitlistRefused->parameters]);
            self::assertSame('The waiting list refuses the request: closed.', $waitlistRefused->getMessage());
        }

        self::assertSame([$entry], $service->release($waitlist->id, 1));
        self::assertSame(EntryStatus::Offered, $entry->status());

        $service->reopen($waitlist->id);
        self::assertSame(EntryStatus::Waiting, $service->join($waitlist->id, 'account-2')->status());
    }

    public function testAnEntryAsksForABoundedNumberOfPlaces(): void
    {
        $service = $this->service(maxPlaces: 3);
        $waitlist = $service->open('session', 'S-1');
        self::assertSame(3, $service->join($waitlist->id, 'account-1', 3)->places());

        try {
            $service->join($waitlist->id, 'account-2', 4);
            self::fail('Four places are too many.');
        } catch (WaitlistRefused $waitlistRefused) {
            self::assertSame([RefusalReason::TooManyPlaces, ['max' => 3]], [$waitlistRefused->reason, $waitlistRefused->parameters]);
        }
    }

    public function testFreedPlacesAreOfferedInTurnWithADeadline(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');
        $first = $this->join($service, $waitlist, 'account-1', 2);
        $second = $this->join($service, $waitlist, 'account-2');
        $third = $this->join($service, $waitlist, 'account-3');
        $this->events->events = [];

        $offered = $service->release($waitlist->id, 3);

        self::assertSame([$first, $second], $offered);
        self::assertSame([EntryStatus::Offered, EntryStatus::Offered, EntryStatus::Waiting], [$first->status(), $second->status(), $third->status()]);
        self::assertEquals($this->clock->now()->modify('+3600 seconds'), $first->offerExpiresAt());
        self::assertEquals($this->clock->now(), $first->offeredAt());
        self::assertEquals(new Standing($first->id, EntryStatus::Offered, null, $first->offerExpiresAt()), $service->standing($first->id));
        self::assertSame(1, $service->standing($third->id)->position);
        self::assertEquals(new ListCount(1, 3, 0), $service->count($waitlist->id));
        self::assertSame(['PlaceOffered '.$first->id, 'PlaceOffered '.$second->id], $this->events->names());
        self::assertEquals(new PlaceOffered($first->id, $waitlist->id), $this->events->at(0));
    }

    public function testPlacesThatFitNobodyAreHeldUntilSomeoneFits(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');
        $group = $this->join($service, $waitlist, 'account-1', 3);
        $single = $this->join($service, $waitlist, 'account-2');

        self::assertSame([], $service->release($waitlist->id, 2));
        self::assertEquals(new ListCount(4, 0, 2), $service->count($waitlist->id));

        self::assertSame([$group], $service->release($waitlist->id, 1));
        self::assertSame([EntryStatus::Offered, EntryStatus::Waiting], [$group->status(), $single->status()]);
        self::assertSame(0, $waitlist->heldPlaces());
    }

    public function testAnotherPolicyPassesOverAGroup(): void
    {
        $service = $this->service(new FirstThatFits());
        $waitlist = $service->open('session', 'S-1');
        $this->join($service, $waitlist, 'account-1', 3);
        $single = $this->join($service, $waitlist, 'account-2');

        self::assertSame([$single], $service->release($waitlist->id, 2));
        self::assertSame(1, $waitlist->heldPlaces());
    }

    public function testAPlaceHeldGoesToTheNextToJoin(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');
        self::assertSame([], $service->release($waitlist->id, 1));

        $entry = $service->join($waitlist->id, 'account-1');

        self::assertSame(EntryStatus::Offered, $entry->status());
        self::assertSame(0, $waitlist->heldPlaces());
        self::assertSame(['EntryJoined '.$entry->id, 'PlaceOffered '.$entry->id], $this->events->names());
    }

    public function testAnAcceptedOfferIsTheApplicationsToHonour(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');
        $entry = $this->join($service, $waitlist, 'account-1');
        $service->release($waitlist->id, 1);
        $this->events->events = [];
        $this->clock->move('+3599 seconds');

        self::assertSame($entry, $service->accept($entry->id));

        self::assertSame(EntryStatus::Accepted, $entry->status());
        self::assertEquals($this->clock->now(), $entry->endedAt());
        self::assertSame(['OfferAccepted '.$entry->id], $this->events->names());
        self::assertSame([$entry->id], $this->entries->refreshed);
        self::assertEquals(new ListCount(0, 0, 0), $service->count($waitlist->id));
        self::assertEquals(new Standing($entry->id, EntryStatus::Accepted, null, null), $service->standing($entry->id));
    }

    public function testOnlyAHeldOfferIsAccepted(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');
        $entry = $this->join($service, $waitlist, 'account-1');

        try {
            $service->accept($entry->id);
            self::fail('A waiting entry has no offer.');
        } catch (WaitlistRefused $waitlistRefused) {
            self::assertSame(RefusalReason::NoOffer, $waitlistRefused->reason);
        }

        $service->release($waitlist->id, 1);
        $this->clock->move('+3600 seconds');

        try {
            $service->accept($entry->id);
            self::fail('The deadline has come.');
        } catch (WaitlistRefused $waitlistRefused) {
            self::assertSame(RefusalReason::OfferExpired, $waitlistRefused->reason);
        }

        self::assertSame(EntryStatus::Offered, $entry->status());
    }

    public function testADeclinedOfferGoesToTheNext(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');
        $first = $this->join($service, $waitlist, 'account-1');
        $second = $this->join($service, $waitlist, 'account-2');
        $service->release($waitlist->id, 1);
        $this->events->events = [];

        self::assertSame($first, $service->decline($first->id));

        self::assertSame([EntryStatus::Declined, EntryStatus::Offered], [$first->status(), $second->status()]);
        self::assertSame(['OfferDeclined '.$first->id, 'PlaceOffered '.$second->id], $this->events->names());
        self::assertSame(0, $waitlist->heldPlaces());
        self::assertSame([$first->id], $this->entries->refreshed);

        $this->expectException(WaitlistRefused::class);
        $service->decline($first->id);
    }

    public function testADeclinedOfferWithNobodyNextIsHeld(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');
        $entry = $this->join($service, $waitlist, 'account-1', 2);
        $service->release($waitlist->id, 2);
        $service->decline($entry->id);

        self::assertSame(2, $waitlist->heldPlaces());
        self::assertContains($waitlist->id.' locked', $this->lists->saved);
    }

    public function testAnEntryLeavesWaitingOrWithItsOffer(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');
        $first = $this->join($service, $waitlist, 'account-1');
        $second = $this->join($service, $waitlist, 'account-2');
        $third = $this->join($service, $waitlist, 'account-3');
        $service->leave($second->id);
        self::assertSame(2, $service->standing($third->id)->position);

        $service->release($waitlist->id, 1);
        $this->events->events = [];
        self::assertSame($first, $service->leave($first->id));

        self::assertSame([EntryStatus::Left, EntryStatus::Left, EntryStatus::Offered], [$first->status(), $second->status(), $third->status()]);
        self::assertSame(['EntryLeft '.$first->id, 'PlaceOffered '.$third->id], $this->events->names());
        self::assertSame([$second->id, $first->id], $this->entries->refreshed);

        $this->expectException(InvalidWaitlist::class);
        $service->leave($first->id);
    }

    public function testAWaitingEntryThatLeavesFreesNoPlace(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');
        $entry = $this->join($service, $waitlist, 'account-1');
        $this->events->events = [];
        $service->leave($entry->id);

        self::assertSame(0, $waitlist->heldPlaces());
        self::assertSame(['EntryLeft '.$entry->id], $this->events->names());
    }

    public function testLapsedOffersGoToTheNextEntries(): void
    {
        $service = $this->service(batch: 2);
        $waitlist = $service->open('session', 'S-1');
        $other = $service->open('session', 'S-2');
        $first = $this->join($service, $waitlist, 'account-1');
        $second = $this->join($service, $waitlist, 'account-2');
        $third = $this->join($service, $waitlist, 'account-3');
        $elsewhere = $this->join($service, $other, 'account-1');
        $service->release($waitlist->id, 1);
        $this->clock->move('+30 minutes');
        $service->release($waitlist->id, 1);
        $service->release($other->id, 1);

        $this->clock->move('+30 minutes');
        $this->events->events = [];
        $this->entries->limits = [];

        self::assertSame(1, $service->lapseExpired());

        self::assertSame([EntryStatus::Lapsed, EntryStatus::Offered, EntryStatus::Offered, EntryStatus::Offered], [$first->status(), $second->status(), $third->status(), $elsewhere->status()]);
        self::assertSame(['OfferLapsed '.$first->id, 'PlaceOffered '.$third->id], $this->events->names());
        self::assertSame([2, 2, 2], $this->entries->limits);
        self::assertSame(0, $service->lapseExpired());

        $this->clock->move('+30 minutes');
        self::assertSame(2, $service->lapseExpired());
        self::assertSame([EntryStatus::Lapsed, EntryStatus::Lapsed], [$second->status(), $elsewhere->status()]);
        self::assertSame(1, $waitlist->heldPlaces());
        self::assertSame(1, $other->heldPlaces());
    }

    public function testAListGoneSinceItsOffersWereReadIsPassedOver(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');
        $other = $service->open('session', 'S-2');
        $this->join($service, $waitlist, 'account-1');
        $kept = $this->join($service, $other, 'account-2');
        $service->release($waitlist->id, 1);
        $service->release($other->id, 1);

        $this->clock->move('+2 hours');
        unset($this->lists->waitlists[$waitlist->id]);

        self::assertSame(1, $service->lapseExpired());
        self::assertSame(EntryStatus::Lapsed, $kept->status());
    }

    public function testADrawRanksTheEntriesByTheSeedThenOffers(): void
    {
        $service = $this->service(batch: 2);
        $waitlist = $service->open('convention', 'C-1', WaitlistMode::Draw);
        $joined = [];

        foreach (['a', 'b', 'c', 'd', 'e'] as $name) {
            $joined[] = $this->join($service, $waitlist, 'account-'.$name);
        }

        self::assertSame([], $service->release($waitlist->id, 2));
        self::assertSame(2, $waitlist->heldPlaces());
        $this->events->events = [];

        self::assertSame($waitlist, $service->draw($waitlist->id, 'published-seed'));

        $expected = $joined;
        usort($expected, static fn (Entry $a, Entry $b): int => hash('sha256', "published-seed\n".$a->id) <=> hash('sha256', "published-seed\n".$b->id));
        self::assertSame(\array_slice($expected, 0, 2), $service->entries($waitlist->id, EntryStatus::Offered));
        self::assertSame(\array_slice($expected, 2, 2), $service->entries($waitlist->id, EntryStatus::Waiting));
        self::assertSame('published-seed', $waitlist->drawSeed());
        self::assertSame(['WaitlistDrawn '.$waitlist->id, 'PlaceOffered '.$expected[0]->id, 'PlaceOffered '.$expected[1]->id], $this->events->names());

        $late = $service->join($waitlist->id, 'account-f');
        self::assertSame(4, $service->standing($late->id)->position);
        self::assertStringStartsWith(Entry::QUEUED, $late->rank());
    }

    public function testADrawWithoutASeedPublishesARandomOne(): void
    {
        $service = $this->service();
        $waitlist = $service->open('convention', 'C-1', WaitlistMode::Draw);
        $service->draw($waitlist->id);

        self::assertMatchesRegularExpression('/^[1-9A-HJ-NP-Za-km-z]{22}$/', (string) $waitlist->drawSeed());
    }

    public function testTheListsAreClosedReopenedAndGivenAnotherWindow(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');

        self::assertSame(600, $service->changeOfferWindow($waitlist->id, 600)->offerWindow());
        self::assertTrue($service->close($waitlist->id)->isClosed());
        self::assertFalse($service->reopen($waitlist->id)->isClosed());
        self::assertSame([$waitlist->id.' unlocked', $waitlist->id.' locked', $waitlist->id.' locked', $waitlist->id.' locked'], $this->lists->saved);
    }

    public function testPlacesHeldAreReclaimed(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');
        $service->release($waitlist->id, 3);

        self::assertSame(2, $service->reclaim($waitlist->id, 2));
        self::assertSame(1, $service->reclaim($waitlist->id, 5));
        self::assertSame(0, $service->reclaim($waitlist->id, 1));
        self::assertSame(0, $waitlist->heldPlaces());

        $this->expectException(InvalidWaitlist::class);
        $this->expectExceptionMessageIsOrContains('At least one place is reclaimed.');
        $service->reclaim($waitlist->id, 0);
    }

    public function testTheEntriesListedAreBounded(): void
    {
        $service = $this->service(batch: 2);
        $waitlist = $service->open('session', 'S-1');

        foreach (['a', 'b', 'c'] as $name) {
            $this->join($service, $waitlist, 'account-'.$name);
        }

        self::assertCount(2, $service->entries($waitlist->id, limit: 50));
        self::assertCount(1, $service->entries($waitlist->id, limit: 1));
        self::assertCount(1, $service->entries($waitlist->id, limit: 0));
    }

    public function testAPolicyThatPicksAnEntryItWasNotGivenIsRefused(): void
    {
        $service = $this->service(new readonly class implements OfferPolicy {
            public function pick(int $places, array $waiting): array
            {
                return [$waiting[0], $waiting[0]];
            }
        });
        $waitlist = $service->open('session', 'S-1');
        $entry = $this->join($service, $waitlist, 'account-1');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('The offer policy picked "'.$entry->id.'", which is not waiting or picked twice.');

        $service->release($waitlist->id, 2);
    }

    public function testAPolicyThatPicksTooManyPlacesIsRefused(): void
    {
        $service = $this->service(new readonly class implements OfferPolicy {
            public function pick(int $places, array $waiting): array
            {
                return $waiting;
            }
        });
        $waitlist = $service->open('session', 'S-1');
        $this->join($service, $waitlist, 'account-1', 2);
        $this->join($service, $waitlist, 'account-2', 2);

        try {
            $service->release($waitlist->id, 3);
            self::fail('Four places are more than three.');
        } catch (UnexpectedValueException $unexpectedValueException) {
            self::assertSame('The offer policy picked more places than are held.', $unexpectedValueException->getMessage());
        }
    }

    public function testAPolicyThatPicksExactlyThePlacesHeldIsHeard(): void
    {
        $service = $this->service(new readonly class implements OfferPolicy {
            public function pick(int $places, array $waiting): array
            {
                return $waiting;
            }
        });
        $waitlist = $service->open('session', 'S-1');
        $this->join($service, $waitlist, 'account-1', 2);
        $this->join($service, $waitlist, 'account-2', 2);

        self::assertCount(2, $service->release($waitlist->id, 4));
        self::assertSame(0, $waitlist->heldPlaces());
    }

    public function testTheDefaultsBoundThePlacesAndTheEntriesRead(): void
    {
        $service = new Waitlists($this->lists, $this->entries, new FirstInLine(), $this->clock);
        $waitlist = $service->open('session', 'S-1');

        self::assertSame(10, $service->join($waitlist->id, 'account-0', 10)->places());

        try {
            $service->join($waitlist->id, 'account-x', 11);
            self::fail('Eleven places are more than ten.');
        } catch (WaitlistRefused $waitlistRefused) {
            self::assertSame(['max' => 10], $waitlistRefused->parameters);
        }

        for ($index = 1; $index <= 100; ++$index) {
            $this->clock->move('+1 second');
            $service->join($waitlist->id, 'account-'.$index);
        }

        self::assertCount(100, $service->entries($waitlist->id, limit: 1_000));
        self::assertCount(100, $service->entries($waitlist->id));

        $wide = $this->service(batch: 500);

        self::assertCount(100, $wide->entries($waitlist->id));
        self::assertCount(101, $wide->entries($waitlist->id, limit: 1_000));
    }

    public function testTheDefaultBatchIsAHundred(): void
    {
        $service = new Waitlists($this->lists, $this->entries, new FirstInLine(), $this->clock);
        $waitlist = $service->open('session', 'S-1');
        $service->join($waitlist->id, 'account-1');
        $this->entries->limits = [];

        $service->release($waitlist->id, 1);
        $this->clock->move('+3 days');
        $service->lapseExpired();

        self::assertSame([100, 100, 100, 100], $this->entries->limits);
    }

    public function testEachChangeIsSavedOnceUnderTheLock(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');
        $first = $this->join($service, $waitlist, 'account-1');
        $second = $this->join($service, $waitlist, 'account-2');
        $listLock = $waitlist->id.' locked';
        $this->lists->saved = [];
        $this->entries->saved = [];
        $service->release($waitlist->id, 1);
        self::assertSame([$first->id.' locked'], $this->entries->saved);
        self::assertSame([$listLock, $listLock], $this->lists->saved);
        $this->lists->saved = [];
        $this->entries->saved = [];
        $service->accept($first->id);
        self::assertSame([$first->id.' locked'], $this->entries->saved);
        self::assertSame([], $this->lists->saved);
        $this->lists->saved = [];
        $this->entries->saved = [];
        $service->leave($second->id);
        self::assertSame([$second->id.' locked'], $this->entries->saved);
        self::assertSame([], $this->lists->saved);

        $this->lists->saved = [];
        $service->release($waitlist->id, 2);
        $service->reclaim($waitlist->id, 1);
        self::assertSame([$listLock, $listLock], $this->lists->saved);
        self::assertSame(1, $waitlist->heldPlaces());
    }

    public function testAnOfferThatFitsNobodySavesTheListOnce(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');
        $this->join($service, $waitlist, 'account-1', 3);
        $this->lists->saved = [];
        $this->entries->saved = [];

        $service->release($waitlist->id, 1);

        self::assertSame([], $this->entries->saved);
        self::assertSame([$waitlist->id.' locked'], $this->lists->saved);
    }

    public function testGivingBackAnOfferSavesTheEntryAndTheList(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');
        $entry = $this->join($service, $waitlist, 'account-1');
        $service->release($waitlist->id, 1);
        $this->lists->saved = [];
        $this->entries->saved = [];

        $service->decline($entry->id);

        self::assertSame([$entry->id.' locked'], $this->entries->saved);
        self::assertSame([$waitlist->id.' locked'], $this->lists->saved);
        self::assertSame(1, $waitlist->heldPlaces());
    }

    public function testLapsingSavesTheEntriesAndTheList(): void
    {
        $service = $this->service();
        $waitlist = $service->open('session', 'S-1');
        $entry = $this->join($service, $waitlist, 'account-1');
        $service->release($waitlist->id, 1);
        $this->clock->move('+2 hours');
        $this->lists->saved = [];
        $this->entries->saved = [];

        self::assertSame(1, $service->lapseExpired());

        self::assertSame([$entry->id.' locked'], $this->entries->saved);
        self::assertSame([$waitlist->id.' locked'], $this->lists->saved);
    }

    public function testADrawSavesEveryEntryRankedAndTheList(): void
    {
        $service = $this->service(batch: 2);
        $waitlist = $service->open('convention', 'C-1', WaitlistMode::Draw);
        $ids = [];

        foreach (['a', 'b', 'c'] as $name) {
            $ids[] = $this->join($service, $waitlist, 'account-'.$name)->id.' locked';
        }
        $this->lists->saved = [];
        $this->entries->saved = [];
        $service->draw($waitlist->id, 'published-seed');

        self::assertEqualsCanonicalizing($ids, $this->entries->saved);
        self::assertSame([$waitlist->id.' locked'], $this->lists->saved);
    }

    public function testThePolicyIsNotAskedWhenNobodyWaits(): void
    {
        $policy = new class implements OfferPolicy {
            public int $asked = 0;

            public function pick(int $places, array $waiting): array
            {
                ++$this->asked;

                return [];
            }
        };
        $service = $this->service($policy);
        $waitlist = $service->open('session', 'S-1');

        self::assertSame([], $service->release($waitlist->id, 2));
        self::assertSame(0, $policy->asked);
    }

    public function testWithoutADispatcherNothingIsDispatched(): void
    {
        $service = new Waitlists($this->lists, $this->entries, new FirstInLine(), $this->clock);
        $waitlist = $service->open('session', 'S-1');

        self::assertSame(EntryStatus::Waiting, $service->join($waitlist->id, 'account-1')->status());
    }
}
