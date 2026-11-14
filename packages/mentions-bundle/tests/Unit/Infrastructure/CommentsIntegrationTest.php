<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\CommentsBundle\Application\Discussion;
use Phil\CommentsBundle\Application\Event\CommentDeleted;
use Phil\CommentsBundle\Application\Event\CommentWritten;
use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\CommentsBundle\Domain\ValueObject\Anchor;
use Phil\MentionsBundle\Application\Event\MemberMentioned;
use Phil\MentionsBundle\Application\Member;
use Phil\MentionsBundle\Application\MentionLimits;
use Phil\MentionsBundle\Application\MentionRecorder;
use Phil\MentionsBundle\Application\Mentions;
use Phil\MentionsBundle\Infrastructure\Comments\CommentMentions;
use Phil\MentionsBundle\Infrastructure\Comments\MemberAuthorDirectory;
use Phil\MentionsBundle\Infrastructure\Comments\RecordCommentMentions;
use Phil\MentionsBundle\Infrastructure\Memory\InMemoryMentionRepository;
use Phil\MentionsBundle\Tests\Unit\Fixture\Directory;
use Phil\MentionsBundle\Tests\Unit\Fixture\Events;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(CommentMentions::class)]
#[CoversClass(MemberAuthorDirectory::class)]
#[CoversClass(RecordCommentMentions::class)]
final class CommentsIntegrationTest extends TestCase
{
    public function testTheCommentsOfADiscussionAreRecordedOnTheirThreadsResource(): void
    {
        $events = new Events();
        $directory = new Directory(['user:3']);
        $mentions = new Mentions(new MentionRecorder(new InMemoryMentionRepository(), $directory, new MockClock(), new MentionLimits(), $events));
        $now = new DateTimeImmutable('2026-11-08 10:00:00 UTC');
        $page = ResourceReference::of('page', '4');
        $thread = Thread::open('t-1', $page, Anchor::of(null, null, 0, 'Hello'), ResourceReference::of('user', '9'), $now);
        $first = Comment::write('c-1', $thread, ResourceReference::of('user', '9'), 'Thoughts, @ada? `@bob`', $now);
        $second = Comment::write('c-2', $thread, ResourceReference::of('user', '1'), 'Me, @ada, and @chloé.', $now);
        $commentMentions = new CommentMentions($mentions, true);

        $commentMentions->discussion(new Discussion($thread, [$first, $second]));
        $commentMentions->discussion(new Discussion($thread, [$first, $second]));

        self::assertEquals([new MemberMentioned(ResourceReference::of('comment', 'c-1'), ResourceReference::of('user', '1'), ResourceReference::of('user', '9'), $page, 'user:1')], $events->dispatched);

        // Read as plain text, the code span of the first comment mentions bob.
        $added = new CommentMentions($mentions)->comment($first, $thread)->added;
        self::assertSame(['user:2'], array_map(static fn (Member $member): string => $member->reference->toString(), $added));
    }

    public function testAForgottenCommentAndTheNamesOfAuthors(): void
    {
        $mentions = new Mentions(new MentionRecorder(new InMemoryMentionRepository(), new Directory(), new MockClock()));
        $now = new DateTimeImmutable();
        $thread = Thread::open('t-1', ResourceReference::of('page', '4'), Anchor::of(null, null, 0, 'Hello'), ResourceReference::of('user', '9'), $now);
        $commentMentions = new CommentMentions($mentions);
        $commentMentions->comment(Comment::write('c-1', $thread, ResourceReference::of('user', '9'), '@ada', $now), $thread);

        self::assertCount(1, $mentions->mentionedIn(CommentMentions::subject('c-1')));
        $commentMentions->forget('c-1');
        self::assertSame([], $mentions->mentionedIn(CommentMentions::subject('c-1')));
        self::assertSame('comment:c-1', CommentMentions::subject('c-1')->toString());

        $names = new MemberAuthorDirectory(new Directory());
        self::assertSame('Ada Lovelace', $names->name(ResourceReference::of('user', '1')));
        self::assertNull($names->name(ResourceReference::of('user', '404')));
    }

    public function testTheBridgeRecordsEachCommentWrittenAndForgetsEachDeleted(): void
    {
        $events = new Events();
        $mentions = new Mentions(new MentionRecorder(new InMemoryMentionRepository(), new Directory(), new MockClock(), new MentionLimits(2), $events));
        $now = new DateTimeImmutable('2026-11-12 10:00:00 UTC');
        $page = ResourceReference::of('page', '4');
        $thread = Thread::open('t-1', $page, null, ResourceReference::of('user', '9'), $now);
        $comment = Comment::write('c-1', $thread, ResourceReference::of('user', '9'), 'On the page, @ada', $now);
        $bridge = new RecordCommentMentions(new CommentMentions($mentions));

        $bridge->written(new CommentWritten($comment, $thread));
        $bridge->written(new CommentWritten($comment, $thread, true));

        self::assertEquals([new MemberMentioned(ResourceReference::of('comment', 'c-1'), ResourceReference::of('user', '1'), ResourceReference::of('user', '9'), $page, 'user:1')], $events->dispatched);
        self::assertCount(1, $mentions->mentionedIn(CommentMentions::subject('c-1')));

        $comment->edit('Too many: @ada @bob @chloé', $now);
        $bridge->written(new CommentWritten($comment, $thread, true));

        self::assertSame([], $mentions->mentionedIn(CommentMentions::subject('c-1')), 'a comment over the limit keeps no mention');
        self::assertSame([], \array_slice($events->dispatched, 1), 'nobody notified for a comment over the limit');

        $comment->edit('@bob', $now);
        $bridge->written(new CommentWritten($comment, $thread, true));
        self::assertSame(['user:2'], array_map(static fn (ResourceReference $member): string => $member->toString(), $mentions->mentionedIn(ResourceReference::of('comment', 'c-1'))));

        $bridge->deleted(new CommentDeleted('c-1', $thread, true));
        self::assertSame([], $mentions->mentionedIn(ResourceReference::fromString('comment:c-1')));
    }
}
