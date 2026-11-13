<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Application;

use Override;
use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\Resource\ResourceReference;

/**
 * The policy of `Comments` used from the application's own code when it configures none: anyone may read and comment,
 * a thread is resolved by whoever opened it, nobody decides on suggestions or moderates. The endpoints are never
 * served on it: they need the application's policy.
 */
final readonly class DefaultCommentPolicy implements CommentPolicy
{
    #[Override]
    public function mayRead(ResourceReference $reader, ResourceReference $subject): bool
    {
        return true;
    }

    #[Override]
    public function mayComment(ResourceReference $author, ResourceReference $subject): bool
    {
        return true;
    }

    #[Override]
    public function mayResolve(ResourceReference $actor, Thread $thread): bool
    {
        return $thread->isBy($actor);
    }

    #[Override]
    public function mayDecide(ResourceReference $actor, Thread $thread): bool
    {
        return false;
    }

    #[Override]
    public function mayModerate(ResourceReference $actor, Comment $comment): bool
    {
        return false;
    }
}
