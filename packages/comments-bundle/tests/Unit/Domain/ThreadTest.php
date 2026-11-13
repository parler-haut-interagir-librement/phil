<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommentsBundle\Domain\Exception\InvalidComment;
use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\CommentsBundle\Domain\Status\SuggestionState;
use Phil\CommentsBundle\Domain\Status\ThreadStatus;
use Phil\CommentsBundle\Domain\ValueObject\Anchor;
use Phil\CommentsBundle\Domain\ValueObject\Member;
use Phil\CommentsBundle\Domain\ValueObject\Suggestion;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Thread::class)]
#[CoversClass(Comment::class)]
#[CoversClass(Suggestion::class)]
#[CoversClass(Member::class)]
final class ThreadTest extends TestCase
{
    public function testAThreadOnTheWholePage(): void
    {
        $thread = Thread::open('t1', ResourceReference::of('page', '1'), null, ResourceReference::of('user', 'ada'), new DateTimeImmutable('2026-11-06 10:00:00 UTC'));

        self::assertNull($thread->anchor());
        self::assertTrue($thread->isOnPage());
        self::assertFalse($thread->isDetached());
        self::assertNull($thread->suggestion());
        self::assertFalse($this->thread()->isOnPage());

        $thread->resolve(ResourceReference::of('user', 'bob'), new DateTimeImmutable('2026-11-06 11:00:00 UTC'));
        self::assertSame([ThreadStatus::Resolved, 'user:bob'], [$thread->status(), $thread->resolvedBy()?->toString()]);

        foreach ([$thread->detach(...), static fn () => $thread->moveTo(Anchor::of(null, null, 0, 'x'))] as $change) {
            try {
                $change();
                self::fail('A thread on the page follows no passage.');
            } catch (InvalidComment $invalidComment) {
                self::assertSame('A thread on the whole page has no passage to follow.', $invalidComment->getMessage());
                self::assertNull($thread->anchor());
                self::assertFalse($thread->isDetached());
            }
        }

        $this->expectExceptionObject(InvalidComment::because('A thread on the whole page has no passage to replace: it suggests nothing.'));
        Thread::open('t2', ResourceReference::of('page', '1'), null, ResourceReference::of('user', 'ada'), new DateTimeImmutable(), '');
    }

    public function testMembersAreNamedByStableIdentifiersNeverEmailAddresses(): void
    {
        $message = 'A member is named by a stable identifier the application gives, never an email address: it cannot contain "@".';
        $email = ResourceReference::of('user', 'ada@example.com');

        foreach ([
            static fn (): Thread => Thread::open('t1', ResourceReference::of('page', '1'), null, $email, new DateTimeImmutable()),
            fn (): Comment => Comment::write('c1', $this->thread(), $email, 'Hi', new DateTimeImmutable()),
            fn () => $this->thread()->resolve($email, new DateTimeImmutable()),
        ] as $call) {
            try {
                $call();
                self::fail($message);
            } catch (InvalidComment $invalidComment) {
                self::assertSame($message, $invalidComment->getMessage());
            }
        }

        $thread = $this->thread();

        try {
            $thread->resolve($email, new DateTimeImmutable());
        } catch (InvalidComment) {
            self::assertSame([ThreadStatus::Open, null], [$thread->status(), $thread->resolvedBy()]);
        }

        self::assertSame('user:ada.example', Member::reference(ResourceReference::of('user', 'ada.example')));
    }

    public function testANewThread(): void
    {
        $thread = $this->thread();

        self::assertSame('t1', $thread->id);
        self::assertSame('page:1', $thread->subject()->toString());
        self::assertSame(Anchor::of('b1', 'text', 2, 'cat', 'a ', ' sat')->toArray(), $thread->anchor()?->toArray());
        self::assertSame('user:ada', $thread->author()->toString());
        self::assertTrue($thread->isBy(ResourceReference::of('user', 'ada')));
        self::assertFalse($thread->isBy(ResourceReference::of('user', 'bob')));
        self::assertSame('2026-11-06T10:00:01+00:00', $thread->createdAt()->format(\DATE_ATOM));
        self::assertSame(ThreadStatus::Open, $thread->status());
        self::assertFalse($thread->isDetached());
        self::assertNull($thread->suggestion());
        self::assertNull($thread->resolvedBy());
        self::assertNull($thread->resolvedAt());
    }

    public function testASuggestion(): void
    {
        self::assertSame(['dog', SuggestionState::Pending], [$this->thread('dog')->suggestion()?->text, $this->thread('dog')->suggestion()?->state]);
        self::assertSame(['', SuggestionState::Pending], [$this->thread('')->suggestion()?->text, $this->thread('')->suggestion()?->state]);
        self::assertSame(str_repeat('é', 2000), $this->thread(str_repeat('é', 2000))->suggestion()?->text);
        self::assertSame(['text' => 'dog', 'state' => 'pending'], $this->thread('dog')->suggestion()?->toArray());
        self::assertSame([true, false, false], [new Suggestion('x', SuggestionState::Pending)->isPending(), new Suggestion('x', SuggestionState::Accepted)->isPending(), new Suggestion('x', SuggestionState::Rejected)->isPending()]);
    }

    public function testResolveAndReopen(): void
    {
        $thread = $this->thread();
        $thread->resolve(ResourceReference::of('user', 'bob'), new DateTimeImmutable('2026-11-06 12:00:00.7 UTC'));
        $thread->resolve(ResourceReference::of('user', 'cy'), new DateTimeImmutable('2026-11-06 13:00:00 UTC'));

        self::assertSame([ThreadStatus::Resolved, 'user:bob', '2026-11-06T12:00:00+00:00'], [$thread->status(), $thread->resolvedBy()?->toString(), $thread->resolvedAt()?->format(\DATE_ATOM)]);

        $thread->reopen();

        self::assertSame([ThreadStatus::Open, null, null], [$thread->status(), $thread->resolvedBy(), $thread->resolvedAt()]);
    }

    public function testDecisionsOnASuggestion(): void
    {
        $accepted = $this->thread('dog');
        $accepted->accept(ResourceReference::of('user', 'bob'), new DateTimeImmutable('2026-11-06 12:00:00 UTC'));

        $rejected = $this->thread('dog');
        $rejected->reject(ResourceReference::of('user', 'cy'), new DateTimeImmutable('2026-11-06 12:00:00 UTC'));

        self::assertSame([SuggestionState::Accepted, ThreadStatus::Resolved, 'user:bob'], [$accepted->suggestion()?->state, $accepted->status(), $accepted->resolvedBy()?->toString()]);
        self::assertSame([SuggestionState::Rejected, ThreadStatus::Resolved, 'user:cy'], [$rejected->suggestion()?->state, $rejected->status(), $rejected->resolvedBy()?->toString()]);

        $accepted->reopen();
        self::assertSame(SuggestionState::Accepted, $accepted->suggestion()?->state);

        foreach ([$accepted, $this->thread()] as $thread) {
            try {
                $thread->reject(ResourceReference::of('user', 'bob'), new DateTimeImmutable());
                self::fail('decided');
            } catch (InvalidComment $invalidComment) {
                self::assertSame('The thread has no suggestion waiting for a decision.', $invalidComment->getMessage());
            }
        }
    }

    public function testAThreadFollowsItsPassage(): void
    {
        $thread = $this->thread();
        $thread->detach();
        self::assertTrue($thread->isDetached());
        self::assertSame(2, $thread->anchor()?->start);

        $thread->moveTo(Anchor::of('b2', 'caption', 5, 'cat', 'x', 'y'));

        self::assertFalse($thread->isDetached());
        self::assertSame(['block' => 'b2', 'field' => 'caption', 'start' => 5, 'end' => 8, 'quote' => 'cat', 'prefix' => 'x', 'suffix' => 'y'], $thread->anchor()?->toArray());
    }

    public function testWhatAThreadCannotBeMadeOf(): void
    {
        foreach ([
            ['', null, 'A thread is identified by 1 to 36 characters.'],
            [str_repeat('t', 37), null, 'A thread is identified by 1 to 36 characters.'],
            ['t1', str_repeat('a', 2001), 'A suggestion is a UTF-8 text of at most 2000 characters.'],
            ['t1', "\xFF", 'A suggestion is a UTF-8 text of at most 2000 characters.'],
        ] as [$id, $suggestion, $message]) {
            try {
                Thread::open($id, ResourceReference::of('page', '1'), Anchor::of(null, null, 0, 'x'), ResourceReference::of('user', 'ada'), new DateTimeImmutable(), $suggestion);
                self::fail($message);
            } catch (InvalidComment $invalidComment) {
                self::assertSame($message, $invalidComment->getMessage());
            }
        }

        self::assertSame(str_repeat('t', 36), Thread::open(str_repeat('t', 36), ResourceReference::of('page', '1'), Anchor::of(null, null, 0, 'x'), ResourceReference::of('user', 'ada'), new DateTimeImmutable())->id);
    }

    public function testAComment(): void
    {
        $comment = Comment::write('c1', $this->thread(), ResourceReference::of('user', 'bob'), "  Hello\n", new DateTimeImmutable('2026-11-06 10:00:00.5 UTC'));

        self::assertSame(['c1', 't1', 'user:bob', 'Hello', '2026-11-06T10:00:00+00:00', null], [$comment->id, $comment->thread, $comment->author()->toString(), $comment->body(), $comment->createdAt()->format(\DATE_ATOM), $comment->editedAt()]);
        self::assertTrue($comment->isBy(ResourceReference::of('user', 'bob')));
        self::assertFalse($comment->isBy(ResourceReference::of('user', 'ada')));

        $comment->edit(' Bye ', new DateTimeImmutable('2026-11-06 11:00:00.9 UTC'));

        self::assertSame(['Bye', '2026-11-06T11:00:00+00:00'], [$comment->body(), $comment->editedAt()?->format(\DATE_ATOM)]);
        self::assertSame(10000, mb_strlen(Comment::write(str_repeat('c', 36), $this->thread(), ResourceReference::of('user', 'bob'), str_repeat('é', 10000), new DateTimeImmutable())->body()));
    }

    public function testWhatACommentCannotBeMadeOf(): void
    {
        $body = 'A comment is a UTF-8 text of 1 to 10000 characters.';

        foreach ([['', 'x', 'A comment is identified by 1 to 36 characters.'], [str_repeat('c', 37), 'x', 'A comment is identified by 1 to 36 characters.'], ['c1', ' ', $body], ['c1', "\xFF", $body], ['c1', str_repeat('a', 10001), $body]] as [$id, $text, $message]) {
            try {
                Comment::write($id, $this->thread(), ResourceReference::of('user', 'bob'), $text, new DateTimeImmutable());
                self::fail($message);
            } catch (InvalidComment $invalidComment) {
                self::assertSame($message, $invalidComment->getMessage());
            }
        }

        $this->expectExceptionMessageIsOrContains($body);
        Comment::write('c1', $this->thread(), ResourceReference::of('user', 'bob'), 'x', new DateTimeImmutable())->edit('', new DateTimeImmutable());
    }

    private function thread(?string $suggestion = null): Thread
    {
        return Thread::open('t1', ResourceReference::of('page', '1'), Anchor::of('b1', 'text', 2, 'cat', 'a ', ' sat'), ResourceReference::of('user', 'ada'), new DateTimeImmutable('2026-11-06 10:00:01.9 UTC'), $suggestion);
    }
}
