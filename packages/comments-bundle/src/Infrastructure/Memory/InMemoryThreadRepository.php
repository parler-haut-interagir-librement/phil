<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Infrastructure\Memory;

use Override;
use Phil\CommentsBundle\Domain\Exception\SuggestionDecided;
use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\CommentsBundle\Domain\Repository\ThreadRepository;
use Phil\Resource\ResourceReference;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The threads in memory, for tests and examples. The bundle tags the store `kernel.reset`: it is emptied between two
 * requests, under PHP-FPM as in a worker, so that nothing a request writes reaches the next one, nor another member. A
 * test that reads what an earlier request wrote uses the Doctrine store on SQLite.
 */
final class InMemoryThreadRepository implements ThreadRepository, ResetInterface
{
    /** @var array<string, Thread> */
    private array $threads = [];

    /** @var array<string, bool> by thread, whether its suggestion was pending when it was last kept */
    private array $pending = [];

    #[Override]
    public function get(string $id): ?Thread
    {
        return $this->threads[$id] ?? null;
    }

    #[Override]
    public function ofSubject(ResourceReference $subject): array
    {
        $found = array_filter($this->threads, static fn (Thread $thread): bool => $thread->subject()->equals($subject));
        usort($found, static function (Thread $a, Thread $b): int {
            $byTime = $a->createdAt()->getTimestamp() <=> $b->createdAt()->getTimestamp();

            return 0 === $byTime ? strcmp($a->id, $b->id) : $byTime;
        });

        return $found;
    }

    #[Override]
    public function save(Thread $thread): void
    {
        $this->threads[$thread->id] = $thread;
        $this->pending[$thread->id] = true === $thread->suggestion()?->isPending();
    }

    #[Override]
    public function decide(Thread $thread): void
    {
        if (true !== ($this->pending[$thread->id] ?? false)) {
            throw SuggestionDecided::onThread($thread->id);
        }

        $this->save($thread);
    }

    #[Override]
    public function remove(Thread $thread): void
    {
        unset($this->threads[$thread->id], $this->pending[$thread->id]);
    }

    #[Override]
    public function reset(): void
    {
        $this->threads = [];
        $this->pending = [];
    }
}
