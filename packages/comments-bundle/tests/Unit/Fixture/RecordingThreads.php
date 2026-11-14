<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\CommentsBundle\Domain\Repository\ThreadRepository;
use Phil\CommentsBundle\Infrastructure\Memory\InMemoryThreadRepository;
use Phil\Resource\ResourceReference;

/**
 * Threads in memory, with the identifiers of those saved, and another request that may decide first.
 */
final class RecordingThreads implements ThreadRepository
{
    /** @var list<string> */
    public array $saved = [];

    /**
     * Another request that decides just after the next read, once.
     *
     * @var ?Closure(InMemoryThreadRepository): void
     */
    public ?Closure $race = null;

    private readonly InMemoryThreadRepository $threads;

    public function __construct()
    {
        $this->threads = new InMemoryThreadRepository();
    }

    #[Override]
    public function get(string $id): ?Thread
    {
        $found = $this->threads->get($id);

        if ($this->race instanceof Closure) {
            ($this->race)($this->threads);
            $this->race = null;
        }

        return $found;
    }

    #[Override]
    public function ofSubject(ResourceReference $subject): array
    {
        return $this->threads->ofSubject($subject);
    }

    #[Override]
    public function save(Thread $thread): void
    {
        $this->saved[] = $thread->id;
        $this->threads->save($thread);
    }

    #[Override]
    public function decide(Thread $thread): void
    {
        $this->saved[] = $thread->id;
        $this->threads->decide($thread);
    }

    #[Override]
    public function remove(Thread $thread): void
    {
        $this->threads->remove($thread);
    }
}
