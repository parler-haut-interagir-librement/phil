<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Application;

use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\Resource\ResourceReference;

/**
 * The application's rules: who may read the threads of a resource, comment on it, resolve and reopen a thread,
 * decide on a suggestion, and edit or delete the comments of others. Everyone may edit and delete their own comments
 * on a resource they may comment on.
 */
interface CommentPolicy
{
    public function mayRead(ResourceReference $reader, ResourceReference $subject): bool;

    public function mayComment(ResourceReference $author, ResourceReference $subject): bool;

    public function mayResolve(ResourceReference $actor, Thread $thread): bool;

    public function mayDecide(ResourceReference $actor, Thread $thread): bool;

    public function mayModerate(ResourceReference $actor, Comment $comment): bool;
}
