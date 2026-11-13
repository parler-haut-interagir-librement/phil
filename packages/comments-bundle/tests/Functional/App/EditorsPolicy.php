<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Functional\App;

use Override;
use Phil\CommentsBundle\Application\CommentPolicy;
use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\Resource\ResourceReference;

/**
 * Everyone reads and comments; the editor `user:ed` resolves, decides and moderates, and authors resolve their own
 * threads.
 */
final readonly class EditorsPolicy implements CommentPolicy
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
        return 'user:ed' === $actor->toString() || $thread->isBy($actor);
    }

    #[Override]
    public function mayDecide(ResourceReference $actor, Thread $thread): bool
    {
        return 'user:ed' === $actor->toString();
    }

    #[Override]
    public function mayModerate(ResourceReference $actor, Comment $comment): bool
    {
        return 'user:ed' === $actor->toString();
    }
}
