<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Domain\Repository;

use Phil\CommentsBundle\Domain\Exception\SuggestionDecided;
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

    /**
     * Keeps the decision just taken on the thread's suggestion, and the resolution that goes with it, only while the
     * stored suggestion is still pending: of two decisions that meet, the second is refused and changes nothing.
     *
     * @throws SuggestionDecided when the stored suggestion is no longer pending
     */
    public function decide(Thread $thread): void;

    public function remove(Thread $thread): void;
}
