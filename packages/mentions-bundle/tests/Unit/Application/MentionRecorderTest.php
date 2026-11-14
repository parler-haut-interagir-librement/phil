<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\MentionsBundle\Application\Event\MemberMentioned;
use Phil\MentionsBundle\Application\Member;
use Phil\MentionsBundle\Application\MentionLimits;
use Phil\MentionsBundle\Application\MentionRecorder;
use Phil\MentionsBundle\Domain\Contract\MentionRepository;
use Phil\MentionsBundle\Domain\Exception\InvalidMention;
use Phil\MentionsBundle\Domain\Exception\MentionsRefused;
use Phil\MentionsBundle\Domain\Model\RecordedMention;
use Phil\MentionsBundle\Domain\ValueObject\Mention;
use Phil\MentionsBundle\Infrastructure\Memory\InMemoryMentionRepository;
use Phil\MentionsBundle\Tests\Unit\Fixture\CountingRepository;
use Phil\MentionsBundle\Tests\Unit\Fixture\Directory;
use Phil\MentionsBundle\Tests\Unit\Fixture\Events;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(MentionRecorder::class)]
#[CoversClass(MemberMentioned::class)]
final class MentionRecorderTest extends TestCase
{
    private InMemoryMentionRepository $repository;

    private Directory $directory;

    private Events $events;

    private MockClock $clock;

    private int $next = 0;

    protected function setUp(): void
    {
        $this->repository = new InMemoryMentionRepository();
        $this->directory = new Directory(['user:3']);
        $this->events = new Events();
        $this->clock = new MockClock('2026-11-08 10:00:00 UTC');
    }

    public function testNewMentionsAreStoredAndAnnounced(): void
    {
        $subject = ResourceReference::of('comment', '7');
        $page = ResourceReference::of('page', '1');
        $changes = $this->recorder()->record($subject, $this->author(), [
            Mention::ofHandle('ADA', 0),
            Mention::ofMember(ResourceReference::of('user', '2'), '@Bob'),
            Mention::ofHandle('ada', 9),
            Mention::ofHandle('nobody', 15),
            Mention::ofHandle('chloé', 20),
            Mention::ofHandle('author', 30),
        ], $page);

        self::assertSame(['user:1', 'user:2'], $this->references($changes->added));
        self::assertSame([], $changes->removed);
        self::assertSame([['resolve', ['ADA', 'nobody', 'chloé', 'author']], ['members', ['user:2']], ['maySee', ['user:1', 'page:1']], ['maySee', ['user:2', 'page:1']], ['maySee', ['user:3', 'page:1']]], $this->directory->calls);
        self::assertEquals([
            new MemberMentioned($subject, ResourceReference::of('user', '1'), $this->author(), $page, 'user:1'),
            new MemberMentioned($subject, ResourceReference::of('user', '2'), $this->author(), $page, 'b-2'),
        ], $this->events->dispatched);

        $stored = $this->repository->ofSubject($subject);
        self::assertSame(['id-1', 'id-2'], array_map(static fn (RecordedMention $mention): string => $mention->id, $stored));
        self::assertSame(['user:9', '2026-11-08 10:00:00'], [$stored[0]->author()->toString(), $stored[0]->createdAt()->format('Y-m-d H:i:s')]);
    }

    public function testTheSameTextAgainChangesNothing(): void
    {
        $subject = ResourceReference::of('comment', '7');
        $recorder = $this->recorder();
        $recorder->record($subject, $this->author(), [Mention::ofHandle('ada', 0)]);

        $this->events->clear();

        $counting = new CountingRepository($this->repository);
        $changes = new MentionRecorder($counting, $this->directory, $this->clock, new MentionLimits(), $this->events)->record($subject, $this->author(), [Mention::ofHandle('Ada', 4)]);

        self::assertTrue($changes->isEmpty());
        self::assertSame(0, $counting->changes);
        self::assertSame([], $this->events->dispatched);
        self::assertSame(['id-1'], array_map(static fn (RecordedMention $mention): string => $mention->id, $this->repository->ofSubject($subject)));
    }

    public function testAMemberAnotherRequestJustAddedIsAnnouncedByThatRequestOnly(): void
    {
        $subject = ResourceReference::of('comment', '7');
        $counting = new CountingRepository($this->repository);
        $counting->meanwhile = static function (MentionRepository $store) use ($subject): void {
            $store->change([RecordedMention::record('other', $subject, ResourceReference::of('user', '1'), ResourceReference::of('user', '9'), new DateTimeImmutable('2026-11-08 09:59:59 UTC'))], []);
        };

        $changes = new MentionRecorder($counting, $this->directory, $this->clock, new MentionLimits(), $this->events, fn (): string => 'id-'.++$this->next)
            ->record($subject, $this->author(), [Mention::ofHandle('ada', 0), Mention::ofHandle('bob', 5)]);

        self::assertSame(['user:2'], $this->references($changes->added));
        self::assertSame([], $changes->removed);
        self::assertEquals([new MemberMentioned($subject, ResourceReference::of('user', '2'), $this->author(), $subject, 'b-2')], $this->events->dispatched);
        self::assertSame(['other', 'id-2'], array_map(static fn (RecordedMention $mention): string => $mention->id, $this->repository->ofSubject($subject)));
    }

    public function testAnEditNotifiesOnlyWhomItAddsAndForgetsWhomItDrops(): void
    {
        $subject = ResourceReference::of('comment', '7');
        $recorder = $this->recorder();
        $recorder->record($subject, $this->author(), [Mention::ofHandle('ada', 0)]);

        $this->events->clear();

        $changes = $recorder->record($subject, $this->author(), [Mention::ofHandle('bob', 0)]);

        self::assertSame(['user:2'], $this->references($changes->added));
        self::assertSame(['user:1'], array_map(static fn (ResourceReference $reference): string => $reference->toString(), $changes->removed));
        self::assertCount(1, $this->events->dispatched);
        self::assertSame(['user:2'], array_map(static fn (ResourceReference $reference): string => $reference->toString(), $recorder->mentionedIn($subject)));
        self::assertEquals(ResourceReference::of('comment', '7'), $this->events->dispatched[0] instanceof MemberMentioned ? $this->events->dispatched[0]->context : null);

        $changes = $recorder->record($subject, $this->author(), []);
        self::assertSame(['user:2'], array_map(static fn (ResourceReference $reference): string => $reference->toString(), $changes->removed));
        self::assertSame([], $recorder->mentionedIn($subject));
    }

    public function testHandlesAreComparedWithoutCaseInAnyScript(): void
    {
        $this->directory->members[] = new Member(ResourceReference::of('user', '4'), 'Élodie', 'Élodie');

        $changes = $this->recorder()->record(ResourceReference::of('comment', '7'), $this->author(), [Mention::ofHandle('élodie', 0), Mention::ofHandle('ÉLODIE', 9)]);

        self::assertSame(['user:4'], self::references($changes->added));
        self::assertSame([['resolve', ['élodie']], ['maySee', ['user:4', 'comment:7']]], $this->directory->calls);
    }

    public function testAMarkAndAHandleOfOneMemberCountOnce(): void
    {
        $changes = $this->recorder()->record(ResourceReference::of('comment', '7'), $this->author(), [Mention::ofHandle('bob', 0), Mention::ofMember(ResourceReference::of('user', '2'), '@Bob'), Mention::ofHandle('ada', 9)]);

        self::assertSame(['user:2', 'user:1'], self::references($changes->added));
    }

    public function testNoMentionAsksTheDirectoryNothing(): void
    {
        $changes = $this->recorder()->record(ResourceReference::of('comment', '7'), $this->author(), []);

        self::assertTrue($changes->isEmpty());
        self::assertSame([], $this->directory->calls);
        self::assertTrue(new MentionRecorder($this->repository, $this->directory, $this->clock)->record(ResourceReference::of('comment', '8'), $this->author(), [Mention::ofHandle('ada', 0)])->added[0]->reference->equals(ResourceReference::of('user', '1')));
    }

    public function testTooManyMembersAreRefusedBeforeTheDirectoryIsAsked(): void
    {
        $recorder = new MentionRecorder($this->repository, $this->directory, $this->clock, new MentionLimits(2));
        $recorder->record(ResourceReference::of('comment', '7'), $this->author(), [Mention::ofHandle('ada', 0), Mention::ofHandle('ADA', 5), Mention::ofHandle('bob', 9)]);

        try {
            $recorder->record(ResourceReference::of('comment', '7'), $this->author(), [Mention::ofHandle('ada', 0), Mention::ofHandle('bob', 5), Mention::ofHandle('zed', 9)]);
            self::fail('three members');
        } catch (MentionsRefused $mentionsRefused) {
            self::assertSame('A text mentions 2 members at most; this one mentions 3.', $mentionsRefused->getMessage());
        }

        self::assertCount(3, $this->directory->calls);
    }

    public function testMentionsOfAMemberAndAForgottenSubject(): void
    {
        $recorder = $this->recorder();
        $ada = ResourceReference::of('user', '1');

        foreach (['1', '2', '3'] as $id) {
            $recorder->record(ResourceReference::of('comment', $id), $this->author(), [Mention::ofHandle('ada', 0)]);
        }

        self::assertSame(['comment:3', 'comment:2'], array_map(static fn (RecordedMention $mention): string => $mention->subject()->toString(), $recorder->mentioning($ada, 2)));
        self::assertCount(1, $recorder->mentioning($ada, 1));
        self::assertCount(3, $recorder->mentioning($ada, 50));
        self::assertCount(3, $recorder->mentioning($ada, 500));

        $recorder->forget(ResourceReference::of('comment', '2'));
        self::assertSame(['comment:3', 'comment:1'], array_map(static fn (RecordedMention $mention): string => $mention->subject()->toString(), $recorder->mentioning($ada, 50)));

        foreach ([0, 501] as $limit) {
            try {
                $recorder->mentioning($ada, $limit);
                self::fail((string) $limit);
            } catch (InvalidMention $invalidMention) {
                self::assertSame('The mentions of a member are read 1 to 500 at a time.', $invalidMention->getMessage());
            }
        }
    }

    public function testAnAuthorIsNeverNotifiedOfTheirOwnMention(): void
    {
        $changes = $this->recorder()->record(ResourceReference::of('comment', '7'), ResourceReference::of('user', '1'), [Mention::ofHandle('ada', 0), Mention::ofHandle('bob', 5)]);

        self::assertSame(['user:2'], $this->references($changes->added));
        self::assertNotContains(['maySee', ['user:1', 'comment:7']], $this->directory->calls);
    }

    private function recorder(): MentionRecorder
    {
        return new MentionRecorder($this->repository, $this->directory, $this->clock, new MentionLimits(), $this->events, fn (): string => 'id-'.++$this->next);
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
