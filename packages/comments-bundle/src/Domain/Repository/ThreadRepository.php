<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Domain\Repository;

use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\Resource\ResourceReference;

/**
 * Where threads are kept.
 */
interface ThreadRepository
{
    public function get(string $id): ?Thread;

    /**
     * The threads of a resource, resolved and detached ones included, oldest first.
     *
     * @return list<Thread>
     */
    public function ofSubject(ResourceReference $subject): array;

    /**
     * Keeps a new thread, or the changes made to one.
     */
    public function save(Thread $thread): void;

    public function remove(Thread $thread): void;
}
