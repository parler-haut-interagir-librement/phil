<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Fixture;

use Override;
use Phil\CommentsBundle\Application\CommentPolicy;
use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\Resource\ResourceReference;

/**
 * A policy by lists: who reads, comments, resolves, decides and moderates, by reference.
 */
final readonly class Policy implements CommentPolicy
{
    /**
     * @param list<string> $readers
     * @param list<string> $writers
     * @param list<string> $resolvers
     * @param list<string> $deciders
     * @param list<string> $moderators
     */
    public function __construct(
        private array $readers = ['user:ada', 'user:bob', 'user:cy'],
        private array $writers = ['user:ada', 'user:bob', 'user:cy'],
        private array $resolvers = ['user:ada'],
        private array $deciders = ['user:ada'],
        private array $moderators = ['user:ada'],
    ) {
    }

    #[Override]
    public function mayRead(ResourceReference $reader, ResourceReference $subject): bool
    {
        return \in_array($reader->toString(), $this->readers, true);
    }

    #[Override]
    public function mayComment(ResourceReference $author, ResourceReference $subject): bool
    {
        return \in_array($author->toString(), $this->writers, true);
    }

    #[Override]
    public function mayResolve(ResourceReference $actor, Thread $thread): bool
    {
        return \in_array($actor->toString(), $this->resolvers, true);
    }

    #[Override]
    public function mayDecide(ResourceReference $actor, Thread $thread): bool
    {
        return \in_array($actor->toString(), $this->deciders, true);
    }

    #[Override]
    public function mayModerate(ResourceReference $actor, Comment $comment): bool
    {
        return \in_array($actor->toString(), $this->moderators, true);
    }
}
