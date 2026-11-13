<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use LogicException;
use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\CommentsBundle\Domain\Repository\CommentRepository;
use Phil\CommentsBundle\Domain\Repository\ThreadRepository;
use Phil\CommentsBundle\Domain\Status\SuggestionState;
use Phil\CommentsBundle\Domain\Status\ThreadStatus;
use Phil\CommentsBundle\Domain\ValueObject\Anchor;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;

/**
 * What every store of threads and comments does.
 */
abstract class RepositoryTestCase extends TestCase
{
    /**
     * @return array{ThreadRepository, CommentRepository}
     */
    abstract protected function stores(): array;

    public function testThreadsAreKeptChangedAndRemoved(): void
    {
        [$threads] = $this->stores();
        $late = $this->thread('t2', 'page:1', '2026-11-06 10:00:05');
        $early = $this->thread('t3', 'page:1', '2026-11-06 10:00:00');
        $tied = $this->thread('t1', 'page:1', '2026-11-06 10:00:05');
        $other = $this->thread('t4', 'page:2', '2026-11-06 09:00:00');

        foreach ([$late, $early, $tied, $other] as $thread) {
            $threads->save($thread);
        }

        self::assertSame(['t3', 't1', 't2'], array_map(static fn (Thread $thread): string => $thread->id, $threads->ofSubject(ResourceReference::of('page', '1'))));
        self::assertSame([], $threads->ofSubject(ResourceReference::of('page', '9')));

        $late->resolve(ResourceReference::of('user', 'bob'), new DateTimeImmutable('2026-11-06 11:00:00 UTC'));
        $late->moveTo(Anchor::of('b2', 'caption', 3, 'dog', 'p', 's'));
        $late->detach();

        $threads->save($late);
        $found = $this->reread($threads, 't2');

        self::assertSame(['page:1', 'user:ada', ThreadStatus::Resolved, 'user:bob', '2026-11-06 11:00:00', true, 'dog?', SuggestionState::Pending], [$found->subject()->toString(), $found->author()->toString(), $found->status(), $found->resolvedBy()?->toString(), $found->resolvedAt()?->format('Y-m-d H:i:s'), $found->isDetached(), $found->suggestion()?->text, $found->suggestion()?->state]);
        self::assertSame(['block' => 'b2', 'field' => 'caption', 'start' => 3, 'end' => 6, 'quote' => 'dog', 'prefix' => 'p', 'suffix' => 's'], $found->anchor()?->toArray());
        self::assertSame('2026-11-06 10:00:05', $found->createdAt()->format('Y-m-d H:i:s'));

        $threads->remove($found);
        self::assertNull($threads->get('t2'));
        self::assertNull($threads->get('t9'));
    }

    public function testAThreadOnTheWholePageIsKept(): void
    {
        [$threads] = $this->stores();
        $threads->save(Thread::open('p1', ResourceReference::of('page', '1'), null, ResourceReference::of('user', 'ada'), new DateTimeImmutable('2026-11-06 10:00:00 UTC')));
        $found = $this->reread($threads, 'p1');

        self::assertSame([null, true, false, null], [$found->anchor(), $found->isOnPage(), $found->isDetached(), $found->suggestion()]);
    }

    public function testCommentsAreKeptChangedAndRemoved(): void
    {
        [$threads, $comments] = $this->stores();
        $one = $this->thread('t1', 'page:1', '2026-11-06 10:00:00');
        $two = $this->thread('t2', 'page:1', '2026-11-06 10:00:00');
        $threads->save($one);
        $threads->save($two);

        foreach ([['c2', $one, '10:00:05'], ['c3', $one, '10:00:00'], ['c1', $one, '10:00:05'], ['c4', $two, '10:00:01'], ['c5', $this->thread('t9', 'page:1', '2026-11-06 10:00:00'), '10:00:00']] as [$id, $thread, $at]) {
            $comments->save(Comment::write($id, $thread, ResourceReference::of('user', 'bob'), 'Body '.$id, new DateTimeImmutable('2026-11-06 '.$at.' UTC')));
        }

        self::assertSame(['c3', 'c1', 'c2'], array_map(static fn (Comment $comment): string => $comment->id, $comments->ofThreads(['t1'])));
        self::assertSame(['c3', 'c4', 'c1', 'c2'], array_map(static fn (Comment $comment): string => $comment->id, $comments->ofThreads(['t1', 't2'])));
        self::assertSame([], $comments->ofThreads([]));

        $edited = $comments->get('c1');
        self::assertInstanceOf(Comment::class, $edited);
        $edited->edit('Changed', new DateTimeImmutable('2026-11-06 12:00:00 UTC'));
        $comments->save($edited);
        $found = $this->rereadComment($comments, 'c1');

        self::assertSame(['t1', 'user:bob', 'Changed', '2026-11-06 10:00:05', '2026-11-06 12:00:00'], [$found->thread, $found->author()->toString(), $found->body(), $found->createdAt()->format('Y-m-d H:i:s'), $found->editedAt()?->format('Y-m-d H:i:s')]);

        $comments->remove($found);
        self::assertNull($comments->get('c1'));
        self::assertSame(['c3', 'c2'], array_map(static fn (Comment $comment): string => $comment->id, $comments->ofThreads(['t1'])));
    }

    /**
     * The thread as the store gives it back, read again from storage when the store has one.
     */
    protected function reread(ThreadRepository $threads, string $id): Thread
    {
        return $threads->get($id) ?? throw new LogicException($id);
    }

    protected function rereadComment(CommentRepository $comments, string $id): Comment
    {
        return $comments->get($id) ?? throw new LogicException($id);
    }

    private function thread(string $id, string $subject, string $at): Thread
    {
        return Thread::open($id, ResourceReference::fromString($subject), Anchor::of('b1', 'text', 0, 'cat'), ResourceReference::of('user', 'ada'), new DateTimeImmutable($at.' UTC'), 'dog?');
    }
}
