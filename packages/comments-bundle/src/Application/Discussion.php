<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Application;

use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\Model\Thread;

/**
 * A thread with its comments, oldest first.
 */
final readonly class Discussion
{
    /**
     * @param list<Comment> $comments
     */
    public function __construct(
        public Thread $thread,
        public array $comments,
    ) {
    }
}
