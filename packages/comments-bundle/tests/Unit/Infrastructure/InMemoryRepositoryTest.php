<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\CommentsBundle\Infrastructure\Memory\InMemoryCommentRepository;
use Phil\CommentsBundle\Infrastructure\Memory\InMemoryThreadRepository;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryThreadRepository::class)]
#[CoversClass(InMemoryCommentRepository::class)]
final class InMemoryRepositoryTest extends RepositoryTestCase
{
    public function testTheStoresAreEmptiedBetweenRequests(): void
    {
        $threads = new InMemoryThreadRepository();
        $comments = new InMemoryCommentRepository();
        $thread = Thread::open('t1', ResourceReference::of('page', '1'), null, ResourceReference::of('user', 'ada'), new DateTimeImmutable());
        $threads->save($thread);
        $comments->save(Comment::write('c1', $thread, ResourceReference::of('user', 'ada'), 'Hi', new DateTimeImmutable()));

        $threads->reset();
        $comments->reset();

        self::assertSame([null, null, [], []], [$threads->get('t1'), $comments->get('c1'), $threads->ofSubject(ResourceReference::of('page', '1')), $comments->ofThreads(['t1'])]);
    }

    #[Override]
    protected function stores(): array
    {
        return [new InMemoryThreadRepository(), new InMemoryCommentRepository()];
    }
}
