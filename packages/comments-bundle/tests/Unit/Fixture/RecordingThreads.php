<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Fixture;

use Override;
use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\CommentsBundle\Domain\Repository\ThreadRepository;
use Phil\CommentsBundle\Infrastructure\Memory\InMemoryThreadRepository;
use Phil\Resource\ResourceReference;

/**
 * Threads in memory, with the identifiers of those saved.
 */
final class RecordingThreads implements ThreadRepository
{
    /** @var list<string> */
    public array $saved = [];

    private readonly InMemoryThreadRepository $threads;

    public function __construct()
    {
        $this->threads = new InMemoryThreadRepository();
    }

    #[Override]
    public function get(string $id): ?Thread
    {
        return $this->threads->get($id);
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
    public function remove(Thread $thread): void
    {
        $this->threads->remove($thread);
    }
}
