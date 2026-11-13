<?php

declare(strict_types=1);

namespace Phil\PollBundle\Infrastructure\Memory;

use Override;
use Phil\PollBundle\Domain\Model\Poll;
use Phil\PollBundle\Domain\Repository\PollRepository;
use Phil\Resource\ResourceReference;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The polls in memory, for tests and demonstrations. The bundle tags the store `kernel.reset`: it is emptied between
 * two requests, under PHP-FPM as in a worker, so that nothing a request writes reaches the next one. A test that reads
 * what an earlier request wrote uses the Doctrine store on SQLite.
 */
final class InMemoryPollRepository implements PollRepository, ResetInterface
{
    /** @var array<string, Poll> */
    private array $polls = [];

    /** @var array<string, int> */
    private array $revisions = [];

    #[Override]
    public function get(string $id): ?Poll
    {
        return $this->polls[$id] ?? null;
    }

    #[Override]
    public function ofSubject(ResourceReference $subject): array
    {
        $polls = array_filter($this->polls, static fn (Poll $poll): bool => $poll->subject()?->equals($subject) ?? false);
        usort($polls, static fn (Poll $a, Poll $b): int => [$a->createdAt()->getTimestamp(), $a->id] <=> [$b->createdAt()->getTimestamp(), $b->id]);

        return $polls;
    }

    #[Override]
    public function save(Poll $poll): void
    {
        $this->polls[$poll->id] = $poll;
    }

    #[Override]
    public function remove(Poll $poll): void
    {
        unset($this->polls[$poll->id], $this->revisions[$poll->id]);
    }

    #[Override]
    public function revision(string $id): int
    {
        return $this->revisions[$id] ?? 0;
    }

    #[Override]
    public function raiseRevision(string $id): void
    {
        if (isset($this->polls[$id])) {
            $this->revisions[$id] = ($this->revisions[$id] ?? 0) + 1;
        }
    }

    #[Override]
    public function reset(): void
    {
        $this->polls = [];
        $this->revisions = [];
    }
}
