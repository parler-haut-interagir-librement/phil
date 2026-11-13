<?php

declare(strict_types=1);

namespace Phil\PollBundle\Domain\Repository;

use Phil\Poll\Ballot;
use Phil\PollBundle\Domain\Exception\AlreadyVoted;
use Phil\PollBundle\Domain\Model\Participation;
use Phil\PollBundle\Domain\Model\Vote;
use Phil\Resource\ResourceReference;

/**
 * Where the ballots of the polls are kept, and the participations of the voters of anonymous polls.
 */
interface VoteRepository
{
    /**
     * The named ballot of a voter in a poll.
     */
    public function ofVoter(string $poll, ResourceReference $voter): ?Vote;

    /**
     * The anonymous ballot of a poll whose receipt has this hash.
     */
    public function withReceipt(string $poll, string $receiptHash): ?Vote;

    /**
     * Whether a voter is recorded as having cast a ballot in an anonymous poll.
     */
    public function hasParticipated(string $poll, ResourceReference $voter): bool;

    /**
     * Keeps a new ballot and, for an anonymous poll, the participation of its voter, both or neither.
     *
     * @throws AlreadyVoted when the voter already has a ballot, or a participation, in the poll
     */
    public function add(Vote $vote, ?Participation $participation = null): void;

    /**
     * Keeps the changes made to a ballot.
     */
    public function save(Vote $vote): void;

    public function remove(Vote $vote): void;

    /**
     * The ballots of a poll, read as the count goes, in no particular order.
     *
     * @return iterable<Ballot>
     */
    public function ballots(string $poll): iterable;

    /**
     * The number of ballots cast in a poll.
     */
    public function count(string $poll): int;

    /**
     * Removes the ballots and the participations of a poll.
     */
    public function removeAllOf(string $poll): void;
}
