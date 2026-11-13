<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Application\Event;

use Phil\CommentsBundle\Domain\Model\Thread;

/**
 * A comment deleted, by the identifier it had, with its thread — removed too when it held no other comment.
 */
final readonly class CommentDeleted
{
    public function __construct(
        public string $comment,
        public Thread $thread,
        public bool $threadRemoved,
    ) {
    }
}
