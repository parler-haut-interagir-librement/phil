<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Application;

use Phil\MentionsBundle\Application\Member;
use Phil\MentionsBundle\Application\MentionLimits;
use Phil\MentionsBundle\Application\MentionRecorder;
use Phil\MentionsBundle\Application\Mentions;
use Phil\MentionsBundle\Domain\Exception\MentionsRefused;
use Phil\MentionsBundle\Domain\Model\RecordedMention;
use Phil\MentionsBundle\Infrastructure\Memory\InMemoryMentionRepository;
use Phil\MentionsBundle\Tests\Unit\Fixture\Directory;
use Phil\Resource\ResourceReference;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Mentions::class)]
final class MentionsTest extends TestCase
{
    public function testATextIsReadAndRecorded(): void
    {
        $mentions = $this->mentions();
        $comment = ResourceReference::of('comment', '7');
        $page = ResourceReference::of('page', '1');

        $changes = $mentions->inText($comment, $this->author(), 'Hi @ada, see `@bob`', true, $page);
        self::assertSame(['user:1'], $this->references($changes->added));

        $changes = $mentions->inText($comment, $this->author(), 'Hi @ada, see `@bob`');
        self::assertSame(['user:2'], $this->references($changes->added));
        self::assertSame(['user:1', 'user:2'], array_map(static fn (ResourceReference $member): string => $member->toString(), $mentions->mentionedIn($comment)));
        self::assertSame(['comment:7'], array_map(static fn (RecordedMention $mention): string => $mention->subject()->toString(), $mentions->mentioning(ResourceReference::of('user', '2'), 10)));
        self::assertCount(1, $mentions->mentioning(ResourceReference::of('user', '2'), 1));

        $mentions->forget($comment);
        self::assertSame([], $mentions->mentionedIn($comment));
    }

    public function testADocumentIsReadAndRecorded(): void
    {
        $document = new Document([new Block('a', 'paragraph', ['text' => '<a data-mention="user:2">@Bob</a> and @ada'])]);

        $changes = $this->mentions()->inDocument(ResourceReference::of('page', '1'), $this->author(), $document, ResourceReference::of('book', '1'));

        self::assertSame(['user:2'], $this->references($changes->added));
    }

    public function testATextTooLongIsRefusedBeforeItIsRead(): void
    {
        $directory = new Directory();
        $mentions = new Mentions(new MentionRecorder(new InMemoryMentionRepository(), $directory, new MockClock()), limits: new MentionLimits(textLength: 8));
        self::assertSame(['user:1'], $this->references($mentions->inText(ResourceReference::of('comment', '1'), $this->author(), 'Hey @ada')->added));

        $this->expectException(MentionsRefused::class);

        try {
            $mentions->inText(ResourceReference::of('comment', '1'), $this->author(), 'Hey @ada!');
        } finally {
            self::assertCount(2, $directory->calls);
        }
    }

    private function mentions(): Mentions
    {
        return new Mentions(new MentionRecorder(new InMemoryMentionRepository(), new Directory(), new MockClock()));
    }

    private function author(): ResourceReference
    {
        return ResourceReference::of('user', '9');
    }

    /**
     * @param list<Member> $members
     *
     * @return list<string>
     */
    private function references(array $members): array
    {
        return array_map(static fn (Member $member): string => $member->reference->toString(), $members);
    }
}
