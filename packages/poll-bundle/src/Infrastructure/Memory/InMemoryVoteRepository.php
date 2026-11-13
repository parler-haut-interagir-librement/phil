<?php

declare(strict_types=1);

namespace Phil\PollBundle\Infrastructure\Memory;

use Override;
use Phil\PollBundle\Domain\Exception\AlreadyVoted;
use Phil\PollBundle\Domain\Model\Participation;
use Phil\PollBundle\Domain\Model\Vote;
use Phil\PollBundle\Domain\Repository\VoteRepository;
use Phil\Resource\ResourceReference;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The ballots and participations in memory, for tests and demonstrations. The bundle tags the store `kernel.reset`: it
 * is emptied between two requests, under PHP-FPM as in a worker, so that no ballot of a request reaches the next one.
 */
final class InMemoryVoteRepository implements VoteRepository, ResetInterface
{
    /** @var array<string, Vote> */
    private array $votes = [];

    /** @var array<string, string> the poll of each participation, by poll and voter */
    private array $participations = [];

    #[Override]
    public function ofVoter(string $poll, ResourceReference $voter): ?Vote
    {
        foreach ($this->votes as $vote) {
            if ($vote->poll === $poll && ($vote->voter()?->equals($voter) ?? false)) {
                return $vote;
            }
        }

        return null;
    }

    #[Override]
    public function withReceipt(string $poll, string $receiptHash): ?Vote
    {
        foreach ($this->votes as $vote) {
            if ($vote->poll === $poll && $vote->receiptHash() === $receiptHash) {
                return $vote;
            }
        }

        return null;
    }

    #[Override]
    public function hasParticipated(string $poll, ResourceReference $voter): bool
    {
        return isset($this->participations[$this->key($poll, $voter)]);
    }

    #[Override]
    public function add(Vote $vote, ?Participation $participation = null): void
    {
        $voter = $vote->voter();

        if (($voter instanceof ResourceReference && $this->ofVoter($vote->poll, $voter) instanceof Vote)
            || ($participation instanceof Participation && $this->hasParticipated($participation->poll, $participation->voter()))) {
            throw AlreadyVoted::in($vote->poll);
        }

        if ($participation instanceof Participation) {
            $this->participations[$this->key($participation->poll, $participation->voter())] = $participation->poll;
        }

        $this->votes[$vote->id] = $vote;
    }

    #[Override]
    public function save(Vote $vote): void
    {
        $this->votes[$vote->id] = $vote;
    }

    #[Override]
    public function remove(Vote $vote): void
    {
        unset($this->votes[$vote->id]);
    }

    #[Override]
    public function ballots(string $poll): iterable
    {
        foreach ($this->votes as $vote) {
            if ($vote->poll === $poll) {
                yield $vote->ballot();
            }
        }
    }

    #[Override]
    public function count(string $poll): int
    {
        return \count(array_filter($this->votes, static fn (Vote $vote): bool => $vote->poll === $poll));
    }

    #[Override]
    public function removeAllOf(string $poll): void
    {
        $this->votes = array_filter($this->votes, static fn (Vote $vote): bool => $vote->poll !== $poll);
        $this->participations = array_filter($this->participations, static fn (string $kept): bool => $kept !== $poll);
    }

    #[Override]
    public function reset(): void
    {
        $this->votes = [];
        $this->participations = [];
    }

    private function key(string $poll, ResourceReference $voter): string
    {
        return $poll."\n".$voter->toString();
    }
}
