<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Application;

use Phil\CommentsBundle\Application\Comments;
use Phil\CommentsBundle\Application\DefaultCommentPolicy;
use Phil\CommentsBundle\Application\Discussion;
use Phil\CommentsBundle\Application\Event\CommentDeleted;
use Phil\CommentsBundle\Application\Event\CommentWritten;
use Phil\CommentsBundle\Application\Exception\CommentRefused;
use Phil\CommentsBundle\Application\Exception\NotFound;
use Phil\CommentsBundle\Domain\Anchoring\Passage;
use Phil\CommentsBundle\Domain\Exception\InvalidComment;
use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\CommentsBundle\Domain\Status\SuggestionState;
use Phil\CommentsBundle\Domain\Status\ThreadStatus;
use Phil\CommentsBundle\Domain\ValueObject\Anchor;
use Phil\CommentsBundle\Tests\Unit\Fixture\Events;
use Phil\CommentsBundle\Tests\Unit\Fixture\Policy;
use Phil\CommentsBundle\Tests\Unit\Fixture\RecordingComments;
use Phil\CommentsBundle\Tests\Unit\Fixture\RecordingThreads;
use Phil\Resource\ResourceReference;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Comments::class)]
#[CoversClass(Discussion::class)]
#[CoversClass(NotFound::class)]
#[CoversClass(CommentRefused::class)]
#[CoversClass(DefaultCommentPolicy::class)]
#[CoversClass(CommentWritten::class)]
#[CoversClass(CommentDeleted::class)]
final class CommentsTest extends TestCase
{
    private RecordingThreads $threads;

    private RecordingComments $comments;

    private MockClock $clock;

    private int $next = 0;

    protected function setUp(): void
    {
        $this->threads = new RecordingThreads();
        $this->comments = new RecordingComments();
        $this->clock = new MockClock('2026-11-06 10:00:00 UTC');
    }

    public function testIdentifiersAreUuidsByDefault(): void
    {
        $discussion = new Comments($this->threads, $this->comments, $this->clock)->open(self::page(), Anchor::of(null, null, 0, 'x'), self::user('ada'), 'Hi');

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $discussion->thread->id);
        self::assertNotSame($discussion->thread->id, $discussion->comments[0]->id);
    }

    public function testAThreadIsOpenedRepliedToAndListed(): void
    {
        $comments = $this->comments();
        $opened = $comments->open(self::page(), Anchor::of('b1', 'text', 0, 'cat'), self::user('ada'), 'Why a cat?', 'dog');
        $this->clock->sleep(5);
        $other = $comments->open(self::page(), Anchor::of('b2', 'text', 0, 'sat'), self::user('bob'), 'Tense');
        $comments->open(ResourceReference::of('page', '2'), Anchor::of('b1', 'text', 0, 'x'), self::user('bob'), 'Elsewhere');
        $this->clock->sleep(5);
        $replied = $comments->reply('id1', self::user('bob'), 'Because.');

        self::assertSame(['id1', 'dog', ['id2']], [$opened->thread->id, $opened->thread->suggestion()?->text, array_map(static fn (Comment $comment): string => $comment->id, $opened->comments)]);
        self::assertSame(['id2', 'id7'], array_map(static fn (Comment $comment): string => $comment->id, $replied->comments));
        self::assertSame(['id3', ['id4']], [$other->thread->id, array_map(static fn (Comment $comment): string => $comment->id, $other->comments)]);
        self::assertSame(['id1', 'id3', 'id5'], $this->threads->saved);

        $list = $comments->discussions(self::page(), self::user('cy'));

        self::assertSame(['id1', 'id3'], array_map(static fn (Discussion $discussion): string => $discussion->thread->id, $list));
        self::assertSame([['Why a cat?', 'Because.'], ['Tense']], array_map(static fn (Discussion $discussion): array => array_map(static fn (Comment $comment): string => $comment->body(), $discussion->comments), $list));
        self::assertSame([0, 1], array_keys($list[0]->comments));
        self::assertSame($opened->thread, $comments->thread('id1'));
    }

    public function testCommentsOnTheWholePageBesideThePassages(): void
    {
        $comments = $this->comments(new Policy(writers: ['user:ada', 'user:bob'], resolvers: ['user:ada']));
        $comments->open(self::page(), Anchor::of(null, null, 0, 'cat'), self::user('ada'), 'On a passage');

        $page = $comments->openOnPage(self::page(), self::user('bob'), 'On the page');
        $comments->reply('id3', self::user('ada'), 'Agreed');

        self::assertSame(['id3', null, true, ['On the page']], [$page->thread->id, $page->thread->anchor(), $page->thread->isOnPage(), array_map(static fn (Comment $comment): string => $comment->body(), $page->comments)]);
        self::assertSame([[false, ['On a passage']], [true, ['On the page', 'Agreed']]], array_map(static fn (Discussion $discussion): array => [$discussion->thread->isOnPage(), array_map(static fn (Comment $comment): string => $comment->body(), $discussion->comments)], $comments->discussions(self::page(), self::user('cy'))));
        self::assertSame(ThreadStatus::Resolved, $comments->resolve('id3', self::user('ada'))->thread->status());

        $this->threads->saved = [];
        self::assertSame(['updated' => 0, 'detached' => 1], $comments->reanchorText(self::page(), 'no passage left'));
        self::assertSame(['id1'], $this->threads->saved);
        self::assertSame([null, false], [$comments->thread('id3')->anchor(), $comments->thread('id3')->isDetached()]);

        try {
            $comments->openOnPage(self::page(), self::user('cy'), 'Not allowed');
            self::fail('cy may not comment');
        } catch (CommentRefused $commentRefused) {
            self::assertSame('The policy does not allow to comment on this resource.', $commentRefused->getMessage());
        }

        self::assertSame(['id1'], $this->threads->saved);
    }

    public function testAThreadOnThePageBeforeThePassagesLeavesThemToBeMoved(): void
    {
        $comments = $this->comments();
        $comments->openOnPage(self::page(), self::user('ada'), 'First, on the page');

        $this->clock->modify('+1 second');
        $comments->open(self::page(), Anchor::of(null, null, 0, 'cat'), self::user('ada'), 'Then on a passage');

        self::assertSame(['updated' => 1, 'detached' => 0], $comments->reanchorText(self::page(), 'a cat'));
    }

    public function testASuggestionIsNeverAppliedFromTheWholePage(): void
    {
        $comments = $this->comments();
        $comments->openOnPage(self::page(), self::user('ada'), 'On the page');

        $this->expectExceptionObject(InvalidComment::because('The thread has no suggestion waiting for a decision.'));
        $comments->applySuggestion('id1', self::user('ada'), new Document());
    }

    public function testEachCommentWrittenEditedOrDeletedIsTold(): void
    {
        $events = new Events();
        $comments = new Comments($this->threads, $this->comments, $this->clock, new Policy(), fn (): string => 'id'.++$this->next, $events);
        $comments->open(self::page(), Anchor::of(null, null, 0, 'x'), self::user('ada'), 'First');
        $comments->openOnPage(self::page(), self::user('bob'), 'Page');
        $comments->reply('id1', self::user('bob'), 'Reply');
        $comments->edit('id5', self::user('bob'), 'Edited');
        $comments->resolve('id1', self::user('ada'));
        $comments->delete('id5', self::user('bob'));
        $comments->delete('id4', self::user('bob'));

        self::assertSame([
            [CommentWritten::class, 'id2', 'id1', false],
            [CommentWritten::class, 'id4', 'id3', false],
            [CommentWritten::class, 'id5', 'id1', false],
            [CommentWritten::class, 'id5', 'id1', true],
            [CommentDeleted::class, 'id5', 'id1', false],
            [CommentDeleted::class, 'id4', 'id3', true],
        ], array_map(static fn (object $event): array => $event instanceof CommentWritten
            ? [CommentWritten::class, $event->comment->id, $event->thread->id, $event->edited]
            : ($event instanceof CommentDeleted ? [CommentDeleted::class, $event->comment, $event->thread->id, $event->threadRemoved] : [$event::class]), $events->dispatched));
        self::assertSame('Edited', $events->dispatched[3] instanceof CommentWritten ? $events->dispatched[3]->comment->body() : null);
    }

    public function testWhatThePolicyRefuses(): void
    {
        $comments = $this->comments(new Policy(readers: [], writers: ['user:ada'], resolvers: [], deciders: [], moderators: []));
        $comments->open(self::page(), Anchor::of(null, null, 0, 'x'), self::user('ada'), 'Hi', 'y');

        foreach ([
            'read these comments' => static fn (): array => $comments->discussions(self::page(), self::user('ada')),
            'comment on this resource' => static fn (): Discussion => $comments->open(self::page(), Anchor::of(null, null, 0, 'x'), self::user('bob'), 'Hi'),
            'comment on this resource ' => static fn (): Discussion => $comments->reply('id1', self::user('bob'), 'Hi'),
            'resolve this thread' => static fn (): Discussion => $comments->resolve('id1', self::user('ada')),
            'reopen this thread' => static fn (): Discussion => $comments->reopen('id1', self::user('ada')),
            'decide on this suggestion' => static fn (): Discussion => $comments->accept('id1', self::user('ada')),
            'decide on this suggestion ' => static fn (): Discussion => $comments->reject('id1', self::user('ada')),
            'decide on this suggestion  ' => static fn (): Document => $comments->applySuggestion('id1', self::user('ada'), new Document()),
            'edit this comment' => static fn (): Discussion => $comments->edit('id2', self::user('bob'), 'x'),
            'delete this comment' => static fn (): ?Discussion => $comments->delete('id2', self::user('bob')),
        ] as $action => $call) {
            try {
                $call();
                self::fail($action);
            } catch (CommentRefused $commentRefused) {
                self::assertSame('The policy does not allow to '.trim($action).'.', $commentRefused->getMessage());
            }
        }

        self::assertSame(['id1'], $this->threads->saved);
    }

    public function testMissingThreadsAndComments(): void
    {
        $comments = $this->comments();

        foreach ([
            'There is no thread "t9".' => static fn (): Discussion => $comments->reply('t9', self::user('ada'), 'x'),
            'There is no thread "t9". ' => static fn (): Discussion => $comments->resolve('t9', self::user('ada')),
            'There is no thread "t9".  ' => static fn (): Document => $comments->applySuggestion('t9', self::user('ada'), new Document()),
            'There is no comment "c9".' => static fn (): Discussion => $comments->edit('c9', self::user('ada'), 'x'),
            'There is no comment "c9". ' => static fn (): ?Discussion => $comments->delete('c9', self::user('ada')),
        ] as $message => $call) {
            try {
                $call();
                self::fail($message);
            } catch (NotFound $notFound) {
                self::assertSame(trim($message), $notFound->getMessage());
            }
        }
    }

    public function testAuthorsEditAndDeleteTheirCommentsModeratorsAnyone(): void
    {
        $comments = $this->comments(new Policy(writers: ['user:ada', 'user:bob'], moderators: ['user:mod']));
        $comments->open(self::page(), Anchor::of(null, null, 0, 'x'), self::user('bob'), 'First');

        $this->clock->sleep(1);
        $comments->reply('id1', self::user('ada'), 'Second');

        $this->comments->saved = [];
        self::assertSame(['Changed', 'Second'], array_map(static fn (Comment $comment): string => $comment->body(), $comments->edit('id2', self::user('bob'), 'Changed')->comments));
        self::assertSame(['id2'], $this->comments->saved);
        self::assertSame('Moderated', $comments->edit('id3', self::user('mod'), 'Moderated')->comments[1]->body());

        foreach ([[self::user('ada'), 'id2'], [self::user('cy'), 'id3']] as [$author, $comment]) {
            try {
                $comments->edit($comment, $author, 'No');
                self::fail($comment);
            } catch (CommentRefused) {
                self::assertSame(['Changed', 'Moderated'], array_map(static fn (Comment $each): string => $each->body(), $comments->discussions(self::page(), self::user('ada'))[0]->comments));
            }
        }

        $left = $comments->delete('id2', self::user('bob'));
        self::assertSame(['id3'], array_map(static fn (Comment $comment): string => $comment->id, $left->comments ?? []));
        self::assertNull($comments->delete('id3', self::user('mod')));
        self::assertNull($this->threads->get('id1'));
        self::assertSame([], $comments->discussions(self::page(), self::user('ada')));
    }

    public function testAnAuthorWhoMayNoLongerCommentKeepsTheirCommentsAsTheyAre(): void
    {
        $this->comments()->open(self::page(), Anchor::of(null, null, 0, 'x'), self::user('cy'), 'Mine');

        $this->expectException(CommentRefused::class);
        $this->comments(new Policy(writers: [], moderators: []))->edit('id2', self::user('cy'), 'Changed');
    }

    public function testResolveReopenAcceptReject(): void
    {
        $comments = $this->comments();
        $comments->open(self::page(), Anchor::of(null, null, 0, 'x'), self::user('bob'), 'Hi', 'y');
        $comments->open(self::page(), Anchor::of(null, null, 0, 'x'), self::user('bob'), 'Hi', 'z');

        $this->clock->sleep(60);

        $resolved = $comments->resolve('id1', self::user('ada'))->thread;
        self::assertSame([ThreadStatus::Resolved, 'user:ada', '2026-11-06 10:01:00'], [$resolved->status(), $resolved->resolvedBy()?->toString(), $resolved->resolvedAt()?->format('Y-m-d H:i:s')]);
        self::assertSame(['Hi'], array_map(static fn (Comment $comment): string => $comment->body(), $comments->reopen('id1', self::user('ada'))->comments));
        self::assertSame(ThreadStatus::Open, $comments->thread('id1')->status());
        self::assertSame([SuggestionState::Accepted, '2026-11-06 10:01:00'], [$comments->accept('id1', self::user('ada'))->thread->suggestion()?->state, $comments->thread('id1')->resolvedAt()?->format('Y-m-d H:i:s')]);
        self::assertSame(SuggestionState::Rejected, $comments->reject('id3', self::user('ada'))->thread->suggestion()?->state);
        self::assertSame(['id1', 'id3', 'id1', 'id1', 'id1', 'id3'], $this->threads->saved);
    }

    public function testASuggestionIsAppliedToADocument(): void
    {
        $comments = $this->comments();
        $comments->open(self::page(), Anchor::of('b1', 'text', 4, 'cat'), self::user('bob'), 'Hi', 'dog');
        $comments->open(self::page(), Anchor::of('b1', 'text', 4, 'cat'), self::user('bob'), 'No suggestion');

        $document = new Document([new Block('b1', 'paragraph', ['text' => 'the <b>cat</b> sat'])]);
        $this->clock->sleep(60);

        $changed = $comments->applySuggestion('id1', self::user('ada'), $document);

        self::assertSame('the <b>dog</b> sat', $changed->blocks[0]->data['text']);
        self::assertSame([SuggestionState::Accepted, 'user:ada', '2026-11-06 10:01:00'], [$comments->thread('id1')->suggestion()?->state, $comments->thread('id1')->resolvedBy()?->toString(), $comments->thread('id1')->resolvedAt()?->format('Y-m-d H:i:s')]);
        self::assertSame(['id1', 'id3', 'id1'], $this->threads->saved);

        foreach (['id3' => 'The thread has no suggestion waiting for a decision.', 'id1' => 'The thread has no suggestion waiting for a decision.'] as $thread => $message) {
            try {
                $comments->applySuggestion($thread, self::user('ada'), $document);
                self::fail($message);
            } catch (InvalidComment $invalidComment) {
                self::assertSame($message, $invalidComment->getMessage());
            }
        }

        $comments->open(self::page(), Anchor::of('b1', 'text', 0, 'cat'), self::user('bob'), 'Stale', 'dog');

        try {
            $comments->applySuggestion('id5', self::user('ada'), $document);
            self::fail('stale');
        } catch (InvalidComment) {
            self::assertSame(SuggestionState::Pending, $comments->thread('id5')->suggestion()?->state);
            self::assertSame(['id1', 'id3', 'id1', 'id5'], $this->threads->saved);
        }
    }

    public function testThreadsFollowTheirPassages(): void
    {
        $comments = $this->comments();
        $comments->open(self::page(), Anchor::of('b1', 'text', 4, 'cat'), self::user('ada'), 'Stays');
        $comments->open(self::page(), Anchor::of('b1', 'text', 8, 'sat'), self::user('ada'), 'Moves');
        $comments->open(self::page(), Anchor::of('b1', 'text', 0, 'gone'), self::user('ada'), 'Goes');
        $comments->open(ResourceReference::of('page', '2'), Anchor::of('b1', 'text', 0, 'sat'), self::user('ada'), 'Elsewhere');

        $this->threads->saved = [];

        self::assertSame(['updated' => 2, 'detached' => 1], $comments->reanchorDocument(self::page(), new Document([new Block('b1', 'paragraph', ['text' => 'the cat <i>has</i> sat'])])));
        self::assertSame(['id1', 'id3', 'id5'], $this->threads->saved);
        self::assertSame([12, true], [$comments->thread('id3')->anchor()?->start, $comments->thread('id5')->isDetached()]);

        $this->threads->saved = [];
        self::assertSame(['updated' => 0, 'detached' => 0], $comments->reanchorDocument(self::page(), new Document([new Block('b1', 'paragraph', ['text' => 'the cat has sat'])])));
        self::assertSame([], $this->threads->saved);

        self::assertSame(['updated' => 1, 'detached' => 2], $comments->reanchorText(self::page(), 'gone'));
        self::assertSame([null, null, 0, false], [$comments->thread('id5')->anchor()?->block, $comments->thread('id5')->anchor()?->field, $comments->thread('id5')->anchor()?->start, $comments->thread('id5')->isDetached()]);
        self::assertSame(['updated' => 1, 'detached' => 1], $comments->reanchor(self::page(), [new Passage(null, null, 'the cat')]));
        self::assertTrue($comments->thread('id3')->isDetached());
        self::assertFalse($comments->thread('id1')->isDetached());
    }

    public function testOnlyThreadsThatChangedAreSaved(): void
    {
        $comments = $this->comments();
        $comments->open(self::page(), Anchor::of('b1', 'text', 0, 'cat', '', ' sat'), self::user('ada'), 'Stays');
        $comments->open(self::page(), Anchor::of('b2', 'text', 0, 'dog'), self::user('ada'), 'Moves');

        $this->threads->saved = [];

        self::assertSame(['updated' => 1, 'detached' => 0], $comments->reanchor(self::page(), [new Passage('b1', 'text', 'cat sat'), new Passage('b2', 'text', 'a dog')]));
        self::assertSame(['id3'], $this->threads->saved);
        self::assertSame(2, $comments->thread('id3')->anchor()?->start);
    }

    public function testTheDefaultPolicy(): void
    {
        $policy = new DefaultCommentPolicy();
        $thread = Thread::open('t1', self::page(), Anchor::of(null, null, 0, 'x'), self::user('ada'), $this->clock->now());
        $comment = Comment::write('c1', $thread, self::user('ada'), 'x', $this->clock->now());

        self::assertSame([true, true, true, false, false, false], [
            $policy->mayRead(self::user('bob'), self::page()),
            $policy->mayComment(self::user('bob'), self::page()),
            $policy->mayResolve(self::user('ada'), $thread),
            $policy->mayResolve(self::user('bob'), $thread),
            $policy->mayDecide(self::user('ada'), $thread),
            $policy->mayModerate(self::user('ada'), $comment),
        ]);
    }

    private function comments(?Policy $policy = null): Comments
    {
        return new Comments($this->threads, $this->comments, $this->clock, $policy ?? new Policy(), fn (): string => 'id'.++$this->next);
    }

    private static function page(): ResourceReference
    {
        return ResourceReference::of('page', '1');
    }

    private static function user(string $name): ResourceReference
    {
        return ResourceReference::of('user', $name);
    }
}
