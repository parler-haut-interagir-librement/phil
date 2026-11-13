<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application;

use DateTimeImmutable;
use Phil\Poll\Ballot;
use Phil\Poll\CountingMethods;
use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\Result;
use Phil\PollBundle\Application\Contract\PollPolicy;
use Phil\PollBundle\Application\Event\PollClosed;
use Phil\PollBundle\Application\Event\PollCreated;
use Phil\PollBundle\Application\Event\PollDeleted;
use Phil\PollBundle\Application\Event\VoteCast;
use Phil\PollBundle\Application\Event\VoteChanged;
use Phil\PollBundle\Application\Event\VoteWithdrawn;
use Phil\PollBundle\Application\Exception\NotFound;
use Phil\PollBundle\Application\Exception\PollRefused;
use Phil\PollBundle\Application\Exception\VoteRefused;
use Phil\PollBundle\Domain\Counting\Contract\ElectingMethod;
use Phil\PollBundle\Domain\Counting\Election;
use Phil\PollBundle\Domain\Exception\AlreadyVoted;
use Phil\PollBundle\Domain\Exception\InvalidPollDefinition;
use Phil\PollBundle\Domain\Model\Participation;
use Phil\PollBundle\Domain\Model\Poll;
use Phil\PollBundle\Domain\Model\Vote;
use Phil\PollBundle\Domain\Repository\PollRepository;
use Phil\PollBundle\Domain\Repository\VoteRepository;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * What the application does with polls: create them, cast, change and withdraw ballots, close, reschedule and delete
 * them, and count their ballots. Every action asks the policy first, and dispatches an event naming the poll once done.
 *
 * With a cache, a count is kept until the next ballot: the key names the revision of the poll's ballots, which every
 * ballot cast, changed or withdrawn raises in the store, so that a count running while a ballot arrives is kept under
 * a revision nobody asks for any more.
 *
 * The service keeps no state between two calls, and is safe to share across the requests of a long-running worker.
 */
final readonly class Polls
{
    public function __construct(
        private PollRepository $polls,
        private VoteRepository $votes,
        private ClockInterface $clock,
        private PollPolicy $policy,
        private CountingMethods $methods,
        private ?EventDispatcherInterface $events = null,
        private ?CacheInterface $cache = null,
        private int $cacheLifetime = 3600,
    ) {
    }

    /**
     * Creates a poll.
     *
     * @throws PollRefused           when the policy refuses the actor
     * @throws InvalidPoll           when the options or the bounds break the rules of a poll
     * @throws InvalidPollDefinition when the question, a label, the counting method, the seats or the dates cannot be kept
     */
    public function create(PollDraft $draft, ResourceReference $actor): Poll
    {
        if (!$this->policy->mayCreate($actor, $draft->subject)) {
            throw PollRefused::because('You may not create this poll.');
        }

        $rules = $draft->rules();
        $counting = $draft->counting ?? CountingMethods::defaultFor($rules->kind);

        if (!$this->methods->has($counting) || !$this->methods->get($counting)->supports($rules->kind)) {
            throw InvalidPollDefinition::because(\sprintf('No counting method named "%s" counts %s ballots.', $counting, $rules->kind->value));
        }

        if (1 !== $draft->seats && !$this->methods->get($counting) instanceof ElectingMethod) {
            throw InvalidPollDefinition::because(\sprintf('The counting method "%s" fills one seat.', $counting));
        }

        $poll = Poll::create(Uuid::v4()->toRfc4122(), $draft->question, $draft->options, $rules, $counting, $draft->anonymous, $draft->results, $actor, $this->now(), $draft->subject, $draft->opensAt, $draft->closesAt, $draft->seats);
        $this->polls->save($poll);
        $this->events?->dispatch(new PollCreated($poll->id));

        return $poll;
    }

    /**
     * @throws NotFound
     */
    public function get(string $id): Poll
    {
        return $this->polls->get($id) ?? throw NotFound::poll($id);
    }

    /**
     * The polls of a resource, oldest first.
     *
     * @return list<Poll>
     */
    public function ofSubject(ResourceReference $subject): array
    {
        return $this->polls->ofSubject($subject);
    }

    /**
     * Casts the voter's ballot. For an anonymous poll, the ballot is kept apart from the voter and the receipt that
     * changes it is returned, once.
     *
     * @param array<mixed> $choices the choices as a form or a request gives them
     *
     * @throws NotFound
     * @throws VoteRefused   when the poll is not open or the policy refuses the voter
     * @throws AlreadyVoted  when the voter already cast a ballot in the poll
     * @throws InvalidBallot when the choices break the rules of the poll
     */
    public function cast(string $pollId, ResourceReference $voter, array $choices): CastVote
    {
        $poll = $this->openFor($pollId, $voter);
        $ballot = $poll->rules()->ballot($choices);
        $id = Uuid::v4()->toRfc4122();

        if ($poll->isAnonymous()) {
            $receipt = sodium_bin2base64(random_bytes(32), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            $this->votes->add(Vote::anonymous($id, $poll->id, $this->hash($receipt), $ballot), Participation::of($poll->id, $voter));
        } else {
            $receipt = null;
            $this->votes->add(Vote::named($id, $poll->id, $voter, $ballot, $this->now()));
        }

        $this->polls->raiseRevision($poll->id);
        $this->events?->dispatch(new VoteCast($poll->id));

        return new CastVote($id, $receipt);
    }

    /**
     * Changes the voter's ballot in a poll that is not anonymous.
     *
     * @param array<mixed> $choices
     *
     * @throws NotFound      when the poll does not exist or the voter has no ballot in it
     * @throws VoteRefused   when the poll is not open, is anonymous, or the policy refuses the voter
     * @throws InvalidBallot when the choices break the rules of the poll
     */
    public function change(string $pollId, ResourceReference $voter, array $choices): void
    {
        $poll = $this->openFor($pollId, $voter);
        $this->named($poll);
        $vote = $this->votes->ofVoter($poll->id, $voter) ?? throw NotFound::ballot($poll->id);

        $this->replace($poll, $vote, $choices);
    }

    /**
     * Changes the anonymous ballot this receipt was given for. The receipt is the voter's proof: whoever holds it may
     * change the ballot, while the poll is open and the policy lets the voter vote.
     *
     * @param array<mixed> $choices
     *
     * @throws NotFound      when the poll does not exist or no ballot has this receipt
     * @throws VoteRefused   when the poll is not open, is not anonymous, or the policy refuses the voter
     * @throws InvalidBallot when the choices break the rules of the poll
     */
    public function changeWithReceipt(string $pollId, ResourceReference $voter, string $receipt, array $choices): void
    {
        $poll = $this->openFor($pollId, $voter);

        if (!$poll->isAnonymous()) {
            throw VoteRefused::because('The ballots of this poll are changed by their voter, without a receipt.');
        }

        $vote = $this->votes->withReceipt($poll->id, $this->hash($receipt)) ?? throw NotFound::ballot($poll->id);

        $this->replace($poll, $vote, $choices);
    }

    /**
     * Withdraws the voter's ballot from a poll that is not anonymous; they may cast another one.
     *
     * @throws NotFound    when the poll does not exist or the voter has no ballot in it
     * @throws VoteRefused when the poll is not open, is anonymous, or the policy refuses the voter
     */
    public function withdraw(string $pollId, ResourceReference $voter): void
    {
        $poll = $this->openFor($pollId, $voter);
        $this->named($poll);
        $vote = $this->votes->ofVoter($poll->id, $voter) ?? throw NotFound::ballot($poll->id);

        $this->votes->remove($vote);
        $this->polls->raiseRevision($poll->id);
        $this->events?->dispatch(new VoteWithdrawn($poll->id));
    }

    /**
     * Whether the voter cast a ballot in the poll, anonymous or not.
     *
     * @throws NotFound
     */
    public function hasVoted(string $pollId, ResourceReference $voter): bool
    {
        $poll = $this->get($pollId);

        return $poll->isAnonymous()
            ? $this->votes->hasParticipated($poll->id, $voter)
            : $this->votes->ofVoter($poll->id, $voter) instanceof Vote;
    }

    /**
     * The voter's ballot, `null` when they cast none or the poll is anonymous: an anonymous ballot is known only to
     * whoever holds its receipt.
     *
     * @throws NotFound
     */
    public function ballotOf(string $pollId, ResourceReference $voter): ?Ballot
    {
        $poll = $this->get($pollId);

        return $poll->isAnonymous() ? null : $this->votes->ofVoter($poll->id, $voter)?->ballot();
    }

    /**
     * The anonymous ballot this receipt was given for, `null` when none has it.
     *
     * @throws NotFound
     */
    public function ballotOfReceipt(string $pollId, string $receipt): ?Ballot
    {
        $poll = $this->get($pollId);

        return $poll->isAnonymous() ? $this->votes->withReceipt($poll->id, $this->hash($receipt))?->ballot() : null;
    }

    /**
     * Closes the poll now.
     *
     * @throws NotFound
     * @throws PollRefused when the policy does not let the actor manage the poll
     */
    public function close(string $pollId, ResourceReference $actor): void
    {
        $poll = $this->managedBy($pollId, $actor);
        $poll->close($this->now());

        $this->polls->save($poll);
        $this->events?->dispatch(new PollClosed($poll->id));
    }

    /**
     * Sets when the poll opens and closes, `null` for at once and for never.
     *
     * @throws NotFound
     * @throws PollRefused           when the policy does not let the actor manage the poll
     * @throws InvalidPollDefinition when it would close at or before it opens
     */
    public function reschedule(string $pollId, ResourceReference $actor, ?DateTimeImmutable $opensAt, ?DateTimeImmutable $closesAt): void
    {
        $poll = $this->managedBy($pollId, $actor);
        $poll->reschedule($opensAt, $closesAt);

        $this->polls->save($poll);
    }

    /**
     * Deletes the poll with its ballots and participations.
     *
     * @throws NotFound
     * @throws PollRefused when the policy does not let the actor manage the poll
     */
    public function delete(string $pollId, ResourceReference $actor): void
    {
        $poll = $this->managedBy($pollId, $actor);
        $this->votes->removeAllOf($poll->id);
        $this->polls->remove($poll);
        $this->events?->dispatch(new PollDeleted($poll->id));
    }

    /**
     * The number of ballots cast in the poll.
     *
     * @throws NotFound
     */
    public function turnout(string $pollId): int
    {
        return $this->votes->count($this->get($pollId)->id);
    }

    /**
     * Counts the ballots of the poll by its method, whoever asks: for code of the application that decides itself
     * what to show. A poll with several seats gives its election as a result.
     *
     * @throws NotFound
     */
    public function results(string $pollId): Result
    {
        $poll = $this->get($pollId);
        $method = $this->methods->get($poll->counting());

        if ($method instanceof ElectingMethod) {
            return $this->electionOf($poll, $method)->toResult();
        }

        return $this->kept('results', $poll, fn (): Result => $method->count($poll->rules(), $this->votes->ballots($poll->id)));
    }

    /**
     * Counts the ballots of a poll whose method fills seats into its election, with every round as it is published,
     * whoever asks.
     *
     * @throws NotFound
     * @throws PollRefused when the poll's method does not fill seats
     */
    public function election(string $pollId): Election
    {
        $poll = $this->get($pollId);
        $method = $this->methods->get($poll->counting());

        if (!$method instanceof ElectingMethod) {
            throw PollRefused::because(\sprintf('The counting method "%s" holds no election.', $poll->counting()));
        }

        return $this->electionOf($poll, $method);
    }

    /**
     * Counts the ballots of the poll for a viewer, `null` for someone not signed in, when the results are theirs to see.
     *
     * @throws NotFound
     * @throws PollRefused when the viewer may not see the results yet
     */
    public function resultsFor(string $pollId, ?ResourceReference $viewer): Result
    {
        $this->mayAsk($pollId, $viewer);

        return $this->results($pollId);
    }

    /**
     * The election of the poll for a viewer, `null` for someone not signed in, when the results are theirs to see.
     *
     * @throws NotFound
     * @throws PollRefused when the viewer may not see the results yet, or the poll's method does not fill seats
     */
    public function electionFor(string $pollId, ?ResourceReference $viewer): Election
    {
        $this->mayAsk($pollId, $viewer);

        return $this->election($pollId);
    }

    /**
     * Whether a viewer may see the results now: the people who manage the poll always may; the others as its
     * visibility says.
     */
    public function maySeeResults(Poll $poll, ?ResourceReference $viewer): bool
    {
        if ($this->policy->mayManage($poll, $viewer)) {
            return true;
        }

        return match ($poll->resultsVisibility()) {
            ResultsVisibility::Always => true,
            ResultsVisibility::AfterClosing => $poll->isClosedAt($this->now()),
            ResultsVisibility::AfterVoting => $poll->isClosedAt($this->now()) || ($viewer instanceof ResourceReference && $this->hasVoted($poll->id, $viewer)),
            ResultsVisibility::Managers => false,
        };
    }

    /**
     * @throws NotFound
     * @throws PollRefused
     */
    private function mayAsk(string $pollId, ?ResourceReference $viewer): void
    {
        if (!$this->maySeeResults($this->get($pollId), $viewer)) {
            throw PollRefused::because('The results of this poll are not yours to see yet.');
        }
    }

    private function electionOf(Poll $poll, ElectingMethod $method): Election
    {
        return $this->kept('election', $poll, fn (): Election => $method->elect($poll->rules(), $this->votes->ballots($poll->id), $poll->seats()));
    }

    /**
     * The count, from the cache when it holds the one of the poll's current revision.
     *
     * @template T of Result|Election
     *
     * @param callable(): T $count
     *
     * @return T
     */
    private function kept(string $what, Poll $poll, callable $count): Result|Election
    {
        if (!$this->cache instanceof CacheInterface) {
            return $count();
        }

        $lifetime = $this->cacheLifetime;

        return $this->cache->get(
            \sprintf('phil_poll.%s.%s.%d', $what, $poll->id, $this->polls->revision($poll->id)),
            static function (ItemInterface $item) use ($count, $lifetime): Result|Election {
                $item->expiresAfter($lifetime);

                return $count();
            },
        );
    }

    /**
     * @throws NotFound
     * @throws VoteRefused
     */
    private function openFor(string $pollId, ResourceReference $voter): Poll
    {
        $poll = $this->get($pollId);

        if (!$poll->isOpenAt($this->now())) {
            throw VoteRefused::because('This poll is not open.');
        }

        if (!$this->policy->mayVote($poll, $voter)) {
            throw VoteRefused::because('You may not vote in this poll.');
        }

        return $poll;
    }

    /**
     * @throws VoteRefused
     */
    private function named(Poll $poll): void
    {
        if ($poll->isAnonymous()) {
            throw VoteRefused::because('The ballots of this poll are anonymous: they are changed with their receipt, and never withdrawn.');
        }
    }

    /**
     * @param array<mixed> $choices
     *
     * @throws InvalidBallot
     */
    private function replace(Poll $poll, Vote $vote, array $choices): void
    {
        $vote->change($poll->rules()->ballot($choices), $this->now());
        $this->votes->save($vote);
        $this->polls->raiseRevision($poll->id);
        $this->events?->dispatch(new VoteChanged($poll->id));
    }

    /**
     * @throws NotFound
     * @throws PollRefused
     */
    private function managedBy(string $pollId, ResourceReference $actor): Poll
    {
        $poll = $this->get($pollId);

        if (!$this->policy->mayManage($poll, $actor)) {
            throw PollRefused::because('You may not manage this poll.');
        }

        return $poll;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }

    private function hash(string $receipt): string
    {
        return hash('sha256', $receipt);
    }
}
