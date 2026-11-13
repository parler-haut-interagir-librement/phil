<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\Poll\BallotKind;
use Phil\Poll\Contract\CountingMethod;
use Phil\Poll\Counting\ChoiceCount;
use Phil\Poll\Counting\InstantRunoff;
use Phil\Poll\CountingMethods;
use Phil\Poll\Exception\InvalidBallot;
use Phil\PollBundle\Application\CastVote;
use Phil\PollBundle\Application\DefaultPollPolicy;
use Phil\PollBundle\Application\Event\PollClosed;
use Phil\PollBundle\Application\Event\PollCreated;
use Phil\PollBundle\Application\Event\PollDeleted;
use Phil\PollBundle\Application\Event\VoteCast;
use Phil\PollBundle\Application\Event\VoteChanged;
use Phil\PollBundle\Application\Event\VoteWithdrawn;
use Phil\PollBundle\Application\Exception\NotFound;
use Phil\PollBundle\Application\Exception\PollRefused;
use Phil\PollBundle\Application\Exception\VoteRefused;
use Phil\PollBundle\Application\PollDraft;
use Phil\PollBundle\Application\Polls;
use Phil\PollBundle\Domain\Counting\Election;
use Phil\PollBundle\Domain\Counting\MeekStv;
use Phil\PollBundle\Domain\Exception\AlreadyVoted;
use Phil\PollBundle\Domain\Exception\InvalidPollDefinition;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\PollBundle\Infrastructure\Memory\InMemoryPollRepository;
use Phil\PollBundle\Infrastructure\Memory\InMemoryVoteRepository;
use Phil\PollBundle\Tests\Unit\Fixture\Policy;
use Phil\PollBundle\Tests\Unit\Fixture\RecordingEvents;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Polls::class)]
#[CoversClass(PollDraft::class)]
#[CoversClass(CastVote::class)]
#[CoversClass(DefaultPollPolicy::class)]
#[CoversClass(NotFound::class)]
#[CoversClass(VoteRefused::class)]
#[CoversClass(PollRefused::class)]
#[CoversClass(PollCreated::class)]
#[CoversClass(VoteCast::class)]
#[CoversClass(VoteChanged::class)]
#[CoversClass(VoteWithdrawn::class)]
#[CoversClass(PollClosed::class)]
#[CoversClass(PollDeleted::class)]
final class PollsTest extends TestCase
{
    private MockClock $clock;

    private InMemoryPollRepository $pollStore;

    private InMemoryVoteRepository $voteStore;

    private Policy $policy;

    private RecordingEvents $events;

    private Polls $polls;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-08 10:00:00 UTC');
        $this->pollStore = new InMemoryPollRepository();
        $this->voteStore = new InMemoryVoteRepository();
        $this->policy = new Policy();
        $this->events = new RecordingEvents();
        $this->polls = new Polls($this->pollStore, $this->voteStore, $this->clock, $this->policy, CountingMethods::defaults(), $this->events);
    }

    public function testAPollIsCreated(): void
    {
        $poll = $this->polls->create(new PollDraft('Tea or coffee?', $this->options('tea', 'coffee'), subject: $this->ref('idea:1')), $this->ref('user:ada'));

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $poll->id);
        self::assertSame($poll, $this->polls->get($poll->id));
        self::assertSame([$poll], $this->polls->ofSubject($this->ref('idea:1')));
        self::assertSame([], $this->polls->ofSubject($this->ref('idea:2')));
        self::assertSame(['choices', BallotKind::Single, false, ResultsVisibility::Always], [$poll->counting(), $poll->rules()->kind, $poll->isAnonymous(), $poll->resultsVisibility()]);
        self::assertSame('2026-11-08 10:00:00', $poll->createdAt()->format('Y-m-d H:i:s'));
        self::assertTrue($poll->isCreatedBy($this->ref('user:ada')));
        self::assertEquals([new PollCreated($poll->id)], $this->events->events);
        self::assertNotSame($poll->id, $this->polls->create(new PollDraft('Q', $this->options('a', 'b')), $this->ref('user:ada'))->id);
    }

    public function testTheDraftReachesThePoll(): void
    {
        $draft = new PollDraft('Rank them', $this->options('a', 'b', 'c'), BallotKind::Ranked, 2, 2, true, 'schulze', true, ResultsVisibility::Managers, null, new DateTimeImmutable('2026-11-09 00:00:00 UTC'), new DateTimeImmutable('2026-11-10 00:00:00 UTC'));
        $poll = $this->polls->create($draft, $this->ref('user:ada'));

        self::assertSame(['schulze', true, ResultsVisibility::Managers], [$poll->counting(), $poll->isAnonymous(), $poll->resultsVisibility()]);
        self::assertSame([BallotKind::Ranked, 2, 2, true], [$poll->rules()->kind, $poll->rules()->minChoices, $poll->rules()->maxChoices, $poll->rules()->abstention]);
        self::assertSame(['2026-11-09', '2026-11-10'], [$poll->opensAt()?->format('Y-m-d'), $poll->closesAt()?->format('Y-m-d')]);
        self::assertSame('instant_runoff', $this->polls->create(new PollDraft('Q', $this->options('a', 'b', 'c'), BallotKind::Ranked), $this->ref('user:ada'))->counting());

        $multiple = new PollDraft('Q', $this->options('a', 'b', 'c'), BallotKind::Multiple);
        self::assertSame([1, 3, false], [$multiple->rules()->minChoices, $multiple->rules()->maxChoices, $multiple->rules()->abstention]);
        self::assertSame([1, 1, true], [new PollDraft('Q', $this->options('a', 'b'), minChoices: 2, maxChoices: 2, abstention: true)->rules()->minChoices, new PollDraft('Q', $this->options('a', 'b'), minChoices: 2, maxChoices: 2)->rules()->maxChoices, new PollDraft('Q', $this->options('a', 'b'), abstention: true)->rules()->abstention]);
    }

    public function testAPollIsCreatedByWhomThePolicyLets(): void
    {
        $this->policy->creates = false;

        $this->expectExceptionObject(PollRefused::because('You may not create this poll.'));

        $this->polls->create(new PollDraft('Q', $this->options('a', 'b')), $this->ref('user:ada'));
    }

    public function testACountingMethodThatCountsTheBallots(): void
    {
        foreach ([[BallotKind::Single, 'borda'], [BallotKind::Ranked, 'approval']] as [$kind, $counting]) {
            try {
                $this->polls->create(new PollDraft('Q', $this->options('a', 'b'), $kind, counting: $counting), $this->ref('user:ada'));
                self::fail($counting);
            } catch (InvalidPollDefinition $invalidPollDefinition) {
                self::assertSame(\sprintf('No counting method named "%s" counts %s ballots.', $counting, $kind->value), $invalidPollDefinition->getMessage());
            }
        }

        self::assertSame([], $this->events->events);
    }

    public function testAnUnknownPoll(): void
    {
        $this->expectExceptionObject(NotFound::poll('nope'));

        $this->polls->get('nope');
    }

    public function testANamedBallotIsCastChangedAndWithdrawn(): void
    {
        $poll = $this->polls->create(new PollDraft('Q', $this->options('a', 'b', 'c'), BallotKind::Multiple), $this->ref('user:ada'));
        $this->events->events = [];

        $cast = $this->polls->cast($poll->id, $this->ref('user:bob'), ['a', 'b']);

        self::assertNull($cast->receipt);
        self::assertSame(['a', 'b'], $this->polls->ballotOf($poll->id, $this->ref('user:bob'))?->choices);
        self::assertSame($cast->vote, $this->voteStore->ofVoter($poll->id, $this->ref('user:bob'))?->id);
        self::assertSame('2026-11-08 10:00:00', $this->voteStore->ofVoter($poll->id, $this->ref('user:bob'))->castAt()?->format('Y-m-d H:i:s'));
        self::assertTrue($this->polls->hasVoted($poll->id, $this->ref('user:bob')));
        self::assertFalse($this->polls->hasVoted($poll->id, $this->ref('user:cid')));
        self::assertNull($this->polls->ballotOf($poll->id, $this->ref('user:cid')));
        self::assertNull($this->polls->ballotOfReceipt($poll->id, 'anything'));
        self::assertSame(1, $this->polls->turnout($poll->id));

        $this->clock->sleep(60);
        $this->polls->change($poll->id, $this->ref('user:bob'), ['c']);
        self::assertSame(['c'], $this->polls->ballotOf($poll->id, $this->ref('user:bob'))?->choices);
        self::assertSame('10:01:00', $this->voteStore->ofVoter($poll->id, $this->ref('user:bob'))?->castAt()?->format('H:i:s'));

        $this->polls->withdraw($poll->id, $this->ref('user:bob'));
        self::assertFalse($this->polls->hasVoted($poll->id, $this->ref('user:bob')));
        self::assertSame(0, $this->polls->turnout($poll->id));
        self::assertEquals([new VoteCast($poll->id), new VoteChanged($poll->id), new VoteWithdrawn($poll->id)], $this->events->events);

        $this->polls->cast($poll->id, $this->ref('user:bob'), ['a']);
        self::assertSame(1, $this->polls->turnout($poll->id));
    }

    public function testANamedVoterVotesOnce(): void
    {
        $poll = $this->polls->create(new PollDraft('Q', $this->options('a', 'b')), $this->ref('user:ada'));
        $this->polls->cast($poll->id, $this->ref('user:bob'), ['a']);

        $this->expectExceptionObject(AlreadyVoted::in($poll->id));

        $this->polls->cast($poll->id, $this->ref('user:bob'), ['b']);
    }

    public function testAnAnonymousBallotIsKeptApartAndChangedByItsReceipt(): void
    {
        $poll = $this->polls->create(new PollDraft('Q', $this->options('a', 'b'), anonymous: true), $this->ref('user:ada'));
        $this->events->events = [];

        $cast = $this->polls->cast($poll->id, $this->ref('user:bob'), ['a']);
        $other = $this->polls->cast($poll->id, $this->ref('user:cid'), ['b']);

        self::assertIsString($cast->receipt);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $cast->receipt);
        self::assertNotSame($cast->receipt, $other->receipt);
        self::assertTrue($this->polls->hasVoted($poll->id, $this->ref('user:bob')));
        self::assertFalse($this->polls->hasVoted($poll->id, $this->ref('user:dan')));
        self::assertNull($this->polls->ballotOf($poll->id, $this->ref('user:bob')));
        self::assertNull($this->voteStore->ofVoter($poll->id, $this->ref('user:bob')));
        self::assertSame(['a'], $this->polls->ballotOfReceipt($poll->id, $cast->receipt)?->choices);
        self::assertNull($this->polls->ballotOfReceipt($poll->id, 'forged'));
        self::assertSame(hash('sha256', $cast->receipt), $this->voteStore->withReceipt($poll->id, hash('sha256', $cast->receipt))?->receiptHash());

        $this->polls->changeWithReceipt($poll->id, $this->ref('user:bob'), $cast->receipt, ['b']);
        self::assertSame(['b'], $this->polls->ballotOfReceipt($poll->id, $cast->receipt)?->choices);
        self::assertEquals([new VoteCast($poll->id), new VoteCast($poll->id), new VoteChanged($poll->id)], $this->events->events);
        self::assertSame(['b'], $this->polls->results($poll->id)->winners());
    }

    public function testAnAnonymousVoterVotesOnce(): void
    {
        $poll = $this->polls->create(new PollDraft('Q', $this->options('a', 'b'), anonymous: true), $this->ref('user:ada'));
        $this->polls->cast($poll->id, $this->ref('user:bob'), ['a']);

        $this->expectExceptionObject(AlreadyVoted::in($poll->id));

        $this->polls->cast($poll->id, $this->ref('user:bob'), ['b']);
    }

    public function testAForgedReceiptChangesNothing(): void
    {
        $poll = $this->polls->create(new PollDraft('Q', $this->options('a', 'b'), anonymous: true), $this->ref('user:ada'));
        $this->polls->cast($poll->id, $this->ref('user:bob'), ['a']);

        $this->expectExceptionObject(NotFound::ballot($poll->id));

        $this->polls->changeWithReceipt($poll->id, $this->ref('user:bob'), 'forged', ['b']);
    }

    public function testTheBallotsOfAnAnonymousPollAreNeitherChangedByTheirVoterNorWithdrawn(): void
    {
        $poll = $this->polls->create(new PollDraft('Q', $this->options('a', 'b'), anonymous: true), $this->ref('user:ada'));
        $this->polls->cast($poll->id, $this->ref('user:bob'), ['a']);
        $message = 'The ballots of this poll are anonymous: they are changed with their receipt, and never withdrawn.';

        foreach ([fn () => $this->polls->change($poll->id, $this->ref('user:bob'), ['b']), fn () => $this->polls->withdraw($poll->id, $this->ref('user:bob'))] as $action) {
            try {
                $action();
                self::fail('Accepted.');
            } catch (VoteRefused $voteRefused) {
                self::assertSame($message, $voteRefused->getMessage());
            }
        }
    }

    public function testANamedBallotIsNotChangedByReceipt(): void
    {
        $poll = $this->polls->create(new PollDraft('Q', $this->options('a', 'b')), $this->ref('user:ada'));

        $this->expectExceptionObject(VoteRefused::because('The ballots of this poll are changed by their voter, without a receipt.'));

        $this->polls->changeWithReceipt($poll->id, $this->ref('user:bob'), 'x', ['a']);
    }

    public function testChangingOrWithdrawingABallotNeverCast(): void
    {
        $poll = $this->polls->create(new PollDraft('Q', $this->options('a', 'b')), $this->ref('user:ada'));

        foreach ([fn () => $this->polls->change($poll->id, $this->ref('user:bob'), ['b']), fn () => $this->polls->withdraw($poll->id, $this->ref('user:bob'))] as $action) {
            try {
                $action();
                self::fail('Accepted.');
            } catch (NotFound $notFound) {
                self::assertSame(\sprintf('No such ballot is kept in the poll "%s".', $poll->id), $notFound->getMessage());
            }
        }
    }

    public function testABallotOutsideTheRulesIsRefused(): void
    {
        $poll = $this->polls->create(new PollDraft('Q', $this->options('a', 'b')), $this->ref('user:ada'));

        try {
            $this->polls->cast($poll->id, $this->ref('user:bob'), ['z']);
            self::fail('Accepted.');
        } catch (InvalidBallot) {
            self::assertSame(0, $this->polls->turnout($poll->id));
        }

        $this->polls->cast($poll->id, $this->ref('user:bob'), ['a']);

        $this->expectException(InvalidBallot::class);

        $this->polls->change($poll->id, $this->ref('user:bob'), ['a', 'b']);
    }

    public function testBallotsAreAcceptedWhileThePollIsOpen(): void
    {
        $poll = $this->polls->create(new PollDraft('Q', $this->options('a', 'b'), opensAt: new DateTimeImmutable('2026-11-08 11:00:00 UTC'), closesAt: new DateTimeImmutable('2026-11-08 12:00:00 UTC')), $this->ref('user:ada'));

        $this->assertRefused(fn (): CastVote => $this->polls->cast($poll->id, $this->ref('user:bob'), ['a']), 'This poll is not open.');
        $this->clock->modify('2026-11-08 11:00:00 UTC');
        $this->polls->cast($poll->id, $this->ref('user:bob'), ['a']);
        $this->clock->modify('2026-11-08 12:00:00 UTC');
        $this->assertRefused(fn () => $this->polls->change($poll->id, $this->ref('user:bob'), ['b']), 'This poll is not open.');
        $this->assertRefused(fn () => $this->polls->withdraw($poll->id, $this->ref('user:bob')), 'This poll is not open.');
    }

    public function testTheVotersThePolicyRefuses(): void
    {
        $this->policy->refusedVoters = ['user:eve'];
        $poll = $this->polls->create(new PollDraft('Q', $this->options('a', 'b'), anonymous: true), $this->ref('user:ada'));
        $cast = $this->polls->cast($poll->id, $this->ref('user:bob'), ['a']);

        $this->assertRefused(fn (): CastVote => $this->polls->cast($poll->id, $this->ref('user:eve'), ['a']), 'You may not vote in this poll.');
        $this->assertRefused(fn () => $this->polls->changeWithReceipt($poll->id, $this->ref('user:eve'), (string) $cast->receipt, ['b']), 'You may not vote in this poll.');
        self::assertSame(['a'], $this->polls->ballotOfReceipt($poll->id, (string) $cast->receipt)?->choices);
    }

    public function testAPollIsClosedRescheduledAndDeletedByItsManagers(): void
    {
        $poll = $this->polls->create(new PollDraft('Q', $this->options('a', 'b'), anonymous: true), $this->ref('user:ada'));
        $other = $this->polls->create(new PollDraft('Q', $this->options('a', 'b'), anonymous: true), $this->ref('user:ada'));
        $this->polls->cast($poll->id, $this->ref('user:bob'), ['a']);
        $this->polls->cast($other->id, $this->ref('user:bob'), ['b']);

        $this->events->events = [];

        foreach ([
            fn () => $this->polls->close($poll->id, $this->ref('user:ada')),
            fn () => $this->polls->reschedule($poll->id, $this->ref('user:ada'), null, null),
            fn () => $this->polls->delete($poll->id, $this->ref('user:ada')),
        ] as $action) {
            try {
                $action();
                self::fail('Accepted.');
            } catch (PollRefused $pollRefused) {
                self::assertSame('You may not manage this poll.', $pollRefused->getMessage());
            }
        }

        $this->polls->reschedule($poll->id, $this->ref('user:boss'), null, new DateTimeImmutable('2026-11-08 18:00:00 UTC'));
        self::assertSame('18:00', $this->polls->get($poll->id)->closesAt()?->format('H:i'));

        $this->clock->sleep(30);
        $this->polls->close($poll->id, $this->ref('user:boss'));
        self::assertSame('10:00:30', $this->polls->get($poll->id)->closedAt()?->format('H:i:s'));

        $this->polls->delete($poll->id, $this->ref('user:boss'));
        self::assertNull($this->pollStore->get($poll->id));
        self::assertSame(0, $this->voteStore->count($poll->id));
        self::assertFalse($this->voteStore->hasParticipated($poll->id, $this->ref('user:bob')));
        self::assertSame(1, $this->polls->turnout($other->id));
        self::assertTrue($this->polls->hasVoted($other->id, $this->ref('user:bob')));
        self::assertEquals([new PollClosed($poll->id), new PollDeleted($poll->id)], $this->events->events);
    }

    public function testTheResults(): void
    {
        $poll = $this->polls->create(new PollDraft('Q', $this->options('a', 'b', 'c'), BallotKind::Ranked), $this->ref('user:ada'));
        $this->polls->cast($poll->id, $this->ref('user:bob'), ['a', 'b']);
        $this->polls->cast($poll->id, $this->ref('user:cid'), ['b']);
        $this->polls->cast($poll->id, $this->ref('user:dan'), ['c', 'b']);
        $this->polls->cast($poll->id, $this->ref('user:eve'), ['b', 'a']);

        $result = $this->polls->results($poll->id);

        self::assertSame([InstantRunoff::NAME, 4, ['b']], [$result->method, $result->ballots, $result->winners()]);
        self::assertSame(['b'], $this->polls->resultsFor($poll->id, null)->winners());
    }

    public function testAnElectionFillsTheSeatsOfItsPoll(): void
    {
        $polls = $this->withMethods([new InstantRunoff(), new MeekStv()]);
        $poll = $polls->create(new PollDraft('Board', $this->options('ann', 'bob', 'cat', 'dan'), BallotKind::Ranked, counting: MeekStv::NAME, results: ResultsVisibility::AfterClosing, seats: 2), $this->ref('user:ada'));
        self::assertSame(2, $poll->seats());

        foreach ([['ann', 'bob'], ['ann', 'cat'], ['bob'], ['dan'], ['ann']] as $i => $ranking) {
            $polls->cast($poll->id, $this->ref('user:v'.$i), $ranking);
        }

        $election = $polls->election($poll->id);
        self::assertSame([MeekStv::NAME, 2, 5, ['ann', 'bob']], [$election->method, $election->seats, $election->ballots, $election->elected]);
        self::assertSame(['ann', 'bob'], $polls->results($poll->id)->winners());
        self::assertSame(['ann', 'bob'], $polls->electionFor($poll->id, $this->ref('user:boss'))->elected);
        $this->assertPollRefused(fn (): Election => $polls->electionFor($poll->id, $this->ref('user:v0')), 'The results of this poll are not yours to see yet.');

        $single = $polls->create(new PollDraft('Q', $this->options('a', 'b', 'c'), BallotKind::Ranked), $this->ref('user:ada'));
        $this->assertPollRefused(static fn (): Election => $polls->election($single->id), 'The counting method "instant_runoff" holds no election.');
    }

    public function testOnlyAMethodThatFillsSeatsTakesSeveral(): void
    {
        $polls = $this->withMethods([new InstantRunoff(), new MeekStv()]);

        $this->expectExceptionObject(InvalidPollDefinition::because('The counting method "instant_runoff" fills one seat.'));

        $polls->create(new PollDraft('Q', $this->options('a', 'b', 'c'), BallotKind::Ranked, seats: 2), $this->ref('user:ada'));
    }

    public function testACountIsKeptUntilTheNextBallot(): void
    {
        $cache = new ArrayAdapter(deepClone: false);
        $polls = new Polls($this->pollStore, $this->voteStore, $this->clock, $this->policy, new CountingMethods([new ChoiceCount(), new InstantRunoff(), new MeekStv()]), $this->events, $cache, 60);
        $poll = $polls->create(new PollDraft('Q', $this->options('a', 'b')), $this->ref('user:ada'));
        $polls->cast($poll->id, $this->ref('user:bob'), ['a']);

        $first = $polls->results($poll->id);
        self::assertSame($first, $polls->results($poll->id));
        self::assertSame(1, $this->pollStore->revision($poll->id));
        self::assertArrayHasKey('phil_poll.results.'.$poll->id.'.1', $cache->getValues());

        $polls->change($poll->id, $this->ref('user:bob'), ['b']);
        self::assertSame(['b'], $polls->results($poll->id)->winners());
        $polls->cast($poll->id, $this->ref('user:cid'), ['a']);
        self::assertSame(2, $polls->results($poll->id)->ballots);
        $polls->withdraw($poll->id, $this->ref('user:cid'));
        self::assertSame(1, $polls->results($poll->id)->ballots);
        self::assertSame(4, $this->pollStore->revision($poll->id));

        $anonymous = $polls->create(new PollDraft('Q', $this->options('a', 'b', 'c'), BallotKind::Ranked, counting: MeekStv::NAME, anonymous: true, seats: 2), $this->ref('user:ada'));
        $receipt = (string) $polls->cast($anonymous->id, $this->ref('user:bob'), ['a', 'b'])->receipt;
        $election = $polls->election($anonymous->id);
        self::assertSame($election, $polls->election($anonymous->id));
        $polls->changeWithReceipt($anonymous->id, $this->ref('user:bob'), $receipt, ['c']);
        self::assertSame('c', $polls->election($anonymous->id)->elected[0]);
        self::assertArrayHasKey('phil_poll.election.'.$anonymous->id.'.2', $cache->getValues());
    }

    public function testAKeptCountExpires(): void
    {
        $cache = new ArrayAdapter(deepClone: false, clock: $this->clock);
        $polls = new Polls($this->pollStore, $this->voteStore, $this->clock, $this->policy, CountingMethods::defaults(), null, $cache, 2);
        $poll = $polls->create(new PollDraft('Q', $this->options('a', 'b')), $this->ref('user:ada'));
        $first = $polls->results($poll->id);

        self::assertSame($first, $polls->results($poll->id));
        $this->clock->sleep(1);
        self::assertSame($first, $polls->results($poll->id));
        $this->clock->sleep(1);
        self::assertNotSame($first, $polls->results($poll->id));
    }

    public function testACountIsKeptAnHourByDefault(): void
    {
        $cache = new ArrayAdapter(deepClone: false, clock: $this->clock);
        $polls = new Polls($this->pollStore, $this->voteStore, $this->clock, $this->policy, CountingMethods::defaults(), null, $cache);
        $poll = $polls->create(new PollDraft('Q', $this->options('a', 'b')), $this->ref('user:ada'));
        $first = $polls->results($poll->id);

        $this->clock->sleep(3599);
        self::assertSame($first, $polls->results($poll->id));
        $this->clock->sleep(1);
        self::assertNotSame($first, $polls->results($poll->id));
    }

    public function testWhoSeesTheResults(): void
    {
        $visibility = [];

        foreach (ResultsVisibility::cases() as $case) {
            $poll = $this->polls->create(new PollDraft('Q', $this->options('a', 'b'), anonymous: ResultsVisibility::AfterVoting === $case, results: $case), $this->ref('user:ada'));
            $this->polls->cast($poll->id, $this->ref('user:bob'), ['a']);
            $visibility[$case->value] = $poll;
        }

        $sees = fn (ResultsVisibility $case, ?string $viewer): bool => $this->polls->maySeeResults($visibility[$case->value], null === $viewer ? null : $this->ref($viewer));

        self::assertSame([true, true, true], [$sees(ResultsVisibility::Always, null), $sees(ResultsVisibility::Always, 'user:cid'), $sees(ResultsVisibility::Always, 'user:boss')]);
        self::assertSame([false, true, false, true], [$sees(ResultsVisibility::AfterVoting, null), $sees(ResultsVisibility::AfterVoting, 'user:bob'), $sees(ResultsVisibility::AfterVoting, 'user:cid'), $sees(ResultsVisibility::AfterVoting, 'user:boss')]);
        self::assertSame([false, false, true], [$sees(ResultsVisibility::AfterClosing, null), $sees(ResultsVisibility::AfterClosing, 'user:bob'), $sees(ResultsVisibility::AfterClosing, 'user:boss')]);
        self::assertSame([false, false, true], [$sees(ResultsVisibility::Managers, null), $sees(ResultsVisibility::Managers, 'user:bob'), $sees(ResultsVisibility::Managers, 'user:boss')]);

        foreach ($visibility as $poll) {
            $this->polls->close($poll->id, $this->ref('user:boss'));
        }

        self::assertSame([true, true, true, false], [$sees(ResultsVisibility::Always, null), $sees(ResultsVisibility::AfterVoting, null), $sees(ResultsVisibility::AfterClosing, 'user:cid'), $sees(ResultsVisibility::Managers, 'user:bob')]);

        $this->expectExceptionObject(PollRefused::because('The results of this poll are not yours to see yet.'));

        $this->polls->resultsFor($visibility[ResultsVisibility::Managers->value]->id, $this->ref('user:bob'));
    }

    public function testAfterVotingCountsTheBallotsOfANamedPoll(): void
    {
        $poll = $this->polls->create(new PollDraft('Q', $this->options('a', 'b'), results: ResultsVisibility::AfterVoting), $this->ref('user:ada'));
        $this->polls->cast($poll->id, $this->ref('user:bob'), ['a']);

        self::assertTrue($this->polls->maySeeResults($poll, $this->ref('user:bob')));
        self::assertFalse($this->polls->maySeeResults($poll, $this->ref('user:cid')));
        self::assertSame(1, $this->polls->resultsFor($poll->id, $this->ref('user:bob'))->ballots);
    }

    public function testClosingAtItsClosingShowsTheResults(): void
    {
        $poll = $this->polls->create(new PollDraft('Q', $this->options('a', 'b'), results: ResultsVisibility::AfterClosing, closesAt: new DateTimeImmutable('2026-11-08 10:00:01 UTC')), $this->ref('user:ada'));

        self::assertFalse($this->polls->maySeeResults($poll, null));
        $this->clock->sleep(1);
        self::assertTrue($this->polls->maySeeResults($poll, null));
    }

    public function testTheDefaultPolicy(): void
    {
        $polls = new Polls($this->pollStore, $this->voteStore, $this->clock, new DefaultPollPolicy(), new CountingMethods([new ChoiceCount()]));
        $poll = $polls->create(new PollDraft('Q', $this->options('a', 'b'), results: ResultsVisibility::Managers), $this->ref('user:ada'));
        $polls->cast($poll->id, $this->ref('user:anyone'), ['a']);
        $polls->change($poll->id, $this->ref('user:anyone'), ['b']);
        self::assertSame(['b'], $polls->ballotOf($poll->id, $this->ref('user:anyone'))?->choices);

        self::assertTrue($polls->maySeeResults($poll, $this->ref('user:ada')));
        self::assertFalse($polls->maySeeResults($poll, $this->ref('user:anyone')));
        self::assertFalse($polls->maySeeResults($poll, null));
        $polls->withdraw($poll->id, $this->ref('user:anyone'));
        self::assertFalse($polls->hasVoted($poll->id, $this->ref('user:anyone')));
        $polls->close($poll->id, $this->ref('user:ada'));
        self::assertTrue($polls->get($poll->id)->isClosedAt($this->clock->now()));
        $polls->delete($poll->id, $this->ref('user:ada'));
        self::assertNull($this->pollStore->get($poll->id));
    }

    /**
     * @param list<CountingMethod> $methods
     */
    private function withMethods(array $methods): Polls
    {
        return new Polls($this->pollStore, $this->voteStore, $this->clock, $this->policy, new CountingMethods($methods), $this->events);
    }

    private function assertPollRefused(callable $action, string $message): void
    {
        try {
            $action();
            self::fail('Accepted.');
        } catch (PollRefused $pollRefused) {
            self::assertSame($message, $pollRefused->getMessage());
        }
    }

    private function assertRefused(callable $action, string $message): void
    {
        try {
            $action();
            self::fail('Accepted.');
        } catch (VoteRefused $voteRefused) {
            self::assertSame($message, $voteRefused->getMessage());
        }
    }

    /**
     * @return list<PollOption>
     */
    private function options(string ...$ids): array
    {
        return array_values(array_map(static fn (string $id): PollOption => PollOption::of($id, strtoupper($id)), $ids));
    }

    private function ref(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }
}
