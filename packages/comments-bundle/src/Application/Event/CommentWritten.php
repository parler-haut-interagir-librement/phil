<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Application\Event;

use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\Model\Thread;

/**
 * A comment written or edited, once saved: the first of a thread, a reply, or a new text. Dispatched in the request
 * that saved it, to the application's event dispatcher when one is given to `Comments`.
 */
final readonly class CommentWritten
{
    public function __construct(
        public Comment $comment,
        public Thread $thread,
        public bool $edited = false,
    ) {
    }
}
