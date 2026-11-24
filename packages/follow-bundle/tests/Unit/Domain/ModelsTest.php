<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Model\Activity;
use Phil\FollowBundle\Domain\Model\Follow;
use Phil\FollowBundle\Domain\Model\FollowRequest;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\Status\RequestKind;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Follow::class)]
#[CoversClass(FollowRequest::class)]
#[CoversClass(Activity::class)]
final class ModelsTest extends TestCase
{
    public function testTheEventsOfAFollowAreKeptAtTheCustomLevelOnly(): void
    {
        $events = new EventPreferences(['reply.created' => FollowLevel::Watching]);

        self::assertSame([], $this->follow(FollowLevel::Watching, $events)->events()->levels);
        self::assertSame($events->levels, $this->follow(FollowLevel::Custom, $events)->events()->levels);
    }

    public function testAChangeSaysWhetherAnythingChanged(): void
    {
        $events = new EventPreferences(['reply.created' => FollowLevel::Watching]);
        $follow = $this->follow(FollowLevel::Watching);

        self::assertFalse($follow->change(FollowLevel::Watching, $events), 'Preferences outside the custom level change nothing.');
        self::assertTrue($follow->change(FollowLevel::Custom, $events));
        self::assertSame(FollowLevel::Custom, $follow->level());
        self::assertSame($events->levels, $follow->events()->levels);
        self::assertFalse($follow->change(FollowLevel::Custom, new EventPreferences(['reply.created' => FollowLevel::Watching])));
        self::assertTrue($follow->change(FollowLevel::Custom, new EventPreferences(['reply.created' => FollowLevel::Ignoring])));
        self::assertTrue($follow->change(FollowLevel::Mentions, $events));
        self::assertSame([], $follow->events()->levels);
    }

    public function testAFollowSaysWhatReachesItsMember(): void
    {
        self::assertTrue($this->follow(FollowLevel::Watching)->notifies('reply.created', false));
        self::assertFalse($this->follow(FollowLevel::Mentions)->notifies('reply.created', false));
        self::assertTrue($this->follow(FollowLevel::Mentions)->notifies('reply.created', true));
        $custom = $this->follow(FollowLevel::Custom, new EventPreferences(['reply.created' => FollowLevel::Watching, 'edit.made' => FollowLevel::Ignoring]));
        self::assertTrue($custom->notifies('reply.created', false));
        self::assertFalse($custom->notifies('edit.made', true));
        self::assertFalse($custom->notifies('poll.opened', false));
        self::assertTrue($custom->notifies('poll.opened', true));
    }

    public function testTheMemberOfAFollowIsAnIdentifier(): void
    {
        $this->expectException(InvalidFollow::class);
        new Follow('an@address', new SubjectReference('thread', '1'), FollowLevel::Watching, new EventPreferences(), new DateTimeImmutable());
    }

    public function testARequestIsNeverApprovedByItsRequester(): void
    {
        $request = new FollowRequest('r1', 'a', new SubjectReference('member', 'b'), RequestKind::Follow, 'b', FollowLevel::Watching, new EventPreferences(), new DateTimeImmutable());
        self::assertSame(['r1', 'a', 'b'], [$request->id, $request->requester, $request->approver]);

        $this->expectExceptionObject(InvalidFollow::because('A member never approves their own request.'));
        new FollowRequest('r1', 'a', new SubjectReference('group', '1'), RequestKind::Follow, 'a', FollowLevel::Watching, new EventPreferences(), new DateTimeImmutable());
    }

    public function testTheIdentifiersOfARequestAreChecked(): void
    {
        foreach ([['r 1', 'a', 'b'], ['r1', 'a a', 'b'], ['r1', 'a', 'b b']] as [$id, $requester, $approver]) {
            try {
                new FollowRequest($id, $requester, new SubjectReference('group', '1'), RequestKind::Mutual, $approver, FollowLevel::Watching, new EventPreferences(), new DateTimeImmutable());
                self::fail('Refused.');
            } catch (InvalidFollow $invalidFollow) {
                self::assertStringContainsString(' is ', $invalidFollow->getMessage());
            }
        }
    }

    public function testAnActivityKeepsItsMentionsOnceAndKnowsThem(): void
    {
        $activity = new Activity('a1', new SubjectReference('thread', '1'), 'reply.created', 'z', ['b', 'c', 'b'], new DateTimeImmutable());

        self::assertSame(['b', 'c'], $activity->mentions);
        self::assertTrue($activity->mentions('c'));
        self::assertFalse($activity->mentions('z'));
        self::assertNull(new Activity('a1', new SubjectReference('thread', '1'), 'reply.created', null, [], new DateTimeImmutable())->actor);
    }

    public function testAnActivityMentionsABoundedNumberOfMembers(): void
    {
        $mentions = array_map(static fn (int $i): string => 'm'.$i, range(1, Activity::MAX_MENTIONS));
        self::assertCount(200, new Activity('a1', new SubjectReference('thread', '1'), 'reply.created', null, $mentions, new DateTimeImmutable())->mentions);

        $this->expectExceptionObject(InvalidFollow::because('An activity mentions 200 members at most.'));
        new Activity('a1', new SubjectReference('thread', '1'), 'reply.created', null, [...$mentions, 'extra'], new DateTimeImmutable());
    }

    public function testTheIdentifiersOfAnActivityAreChecked(): void
    {
        foreach ([['a 1', 'reply.created', null, []], ['a1', 'Reply', null, []], ['a1', 'reply.created', 'z z', []], ['a1', 'reply.created', null, ['m m']]] as [$id, $event, $actor, $mentions]) {
            try {
                new Activity($id, new SubjectReference('thread', '1'), $event, $actor, $mentions, new DateTimeImmutable());
                self::fail('Refused.');
            } catch (InvalidFollow $invalidFollow) {
                self::assertStringContainsString(' is ', $invalidFollow->getMessage());
            }
        }
    }

    private function follow(FollowLevel $level, EventPreferences $events = new EventPreferences()): Follow
    {
        return new Follow('a', new SubjectReference('thread', '1'), $level, $events, new DateTimeImmutable());
    }
}
