<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\FollowBundle\Application\Activities;
use Phil\FollowBundle\Application\FollowTypes;
use Phil\FollowBundle\Application\Handler\FanOutActivityHandler;
use Phil\FollowBundle\Application\Message\FanOutActivity;
use Phil\FollowBundle\Application\RecipientResolver;
use Phil\FollowBundle\Application\SubjectChain;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Model\Follow;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;
use Phil\FollowBundle\Tests\Fixtures\RecordingBus;
use Phil\FollowBundle\Tests\Fixtures\Stage;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(FanOutActivityHandler::class)]
#[CoversClass(Activities::class)]
#[CoversClass(FanOutActivity::class)]
#[CoversClass(RecipientResolver::class)]
#[CoversClass(SubjectChain::class)]
final class FanOutActivityHandlerTest extends TestCase
{
    private Stage $stage;

    protected function setUp(): void
    {
        $this->stage = new Stage();
    }

    public function testManyFollowersAreServedInABoundedNumberOfStatements(): void
    {
        $thread = Stage::subject('thread', '1');
        $forum = Stage::subject('forum', '9');
        $this->stage->ports->parents['thread:1'] = [$forum];
        $rows = [];

        for ($i = 0; $i < 2400; ++$i) {
            $rows[] = \sprintf("('m%04d', 'forum', '9', 'watching', '[]', '2026-11-01 00:00:00')", $i);
        }

        $this->stage->connection->executeStatement('INSERT INTO phil_follows (member, subject_type, subject_id, level, events, followed_at) VALUES '.implode(', ', $rows));
        $this->stage->log->statements = 0;

        $this->stage->activities($this->stage->handler(batchSize: 1000))->publish($thread, 'reply.created', 'm0000');

        // The activity kept and read (2), then for each of the 3 pages: the members, their follows, the claim (2), the
        // marks read, the marks written (2): 7 a page, plus the empty page that ends the run.
        self::assertLessThanOrEqual(2 + 3 * 7 + 1, $this->stage->log->statements);
        self::assertCount(2399, $this->stage->recipients(FollowTypes::ACTIVITY));
        self::assertCount(3, $this->stage->sent(FollowTypes::ACTIVITY), 'One call to the notifications a page.');
        self::assertNotContains('m0000', $this->stage->recipients(FollowTypes::ACTIVITY), 'The actor hears nothing of their own activity.');
    }

    public function testWithABusEachFullPageEndsWithTheMessageOfTheNextOne(): void
    {
        $bus = new RecordingBus();
        $subject = Stage::subject('thread', '1');

        foreach (['a', 'b', 'c'] as $member) {
            $this->follow($member, $subject);
        }

        $activity = $this->stage->activities($this->stage->handler(batchSize: 2, bus: $bus), $bus)->publish($subject, 'reply.created');
        self::assertEquals([new FanOutActivity($activity)], $bus->messages);
        self::assertSame([], $this->stage->sent(), 'Nothing is sent before the worker reads the message.');

        $handler = $this->stage->handler(batchSize: 2, bus: $bus);
        $handler($bus->of(FanOutActivity::class)[0]);
        self::assertSame(['a', 'b'], $this->stage->recipients(FollowTypes::ACTIVITY));
        self::assertEquals(new FanOutActivity($activity, 'b'), $bus->messages[1]);

        $handler($bus->of(FanOutActivity::class)[1]);
        self::assertSame(['a', 'b', 'c'], $this->stage->recipients(FollowTypes::ACTIVITY));
        self::assertCount(2, $bus->messages, 'A page that is not full is the last.');
    }

    public function testAPageExactlyFullIsFollowedByAnEmptyOne(): void
    {
        $subject = Stage::subject('thread', '1');
        $this->follow('a', $subject);
        $this->follow('b', $subject);

        $this->stage->activities($this->stage->handler(batchSize: 2))->publish($subject, 'reply.created');

        self::assertSame(['a', 'b'], $this->stage->recipients(FollowTypes::ACTIVITY));
    }

    public function testARetriedMessageSendsToNoOneTwice(): void
    {
        $subject = Stage::subject('thread', '1');
        $this->follow('a', $subject);
        $this->follow('b', $subject);
        $id = $this->stage->activities()->publish($subject, 'reply.created');

        $this->stage->handler()(new FanOutActivity($id));
        $this->stage->activities()->publish($subject, 'reply.created', id: $id);

        self::assertSame(['a', 'b'], $this->stage->recipients(FollowTypes::ACTIVITY));
        self::assertSame([], $this->stage->recipients(FollowTypes::QUIET));
    }

    public function testTheNotificationCarriesIdentifiersAndTheKeyOfDuplicates(): void
    {
        $subject = Stage::subject('thread', '1');
        $this->follow('a', $subject);
        $id = $this->stage->activities()->publish($subject, 'reply.created', 'z', id: 'reply-7');

        $sent = $this->stage->sent(FollowTypes::ACTIVITY)[0];
        self::assertSame('reply-7', $id);
        self::assertSame(['thread', '1'], [$sent->subjectType, $sent->subjectId]);
        self::assertSame(['activity' => 'reply-7', 'event' => 'reply.created'], $sent->data);
        self::assertSame('z', $sent->actor);
        self::assertSame('follow:reply-7', $sent->dedupeKey);
    }

    public function testAFailedCallGivesItsClaimsBackForTheNextAttempt(): void
    {
        $subject = Stage::subject('thread', '1');
        $this->follow('a', $subject);
        $this->stage->notificationBus->failNext = new RuntimeException('The transport is down.');

        try {
            $this->stage->activities()->publish($subject, 'reply.created', id: 'r1');
            self::fail('The failure goes up, for the worker to retry.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The transport is down.', $runtimeException->getMessage());
        }

        self::assertSame(0, $this->stage->number('SELECT COUNT(*) FROM phil_follow_deliveries'));
        self::assertSame(0, $this->stage->number('SELECT COUNT(*) FROM phil_follow_unseen'));

        $this->stage->handler()(new FanOutActivity('r1'));
        self::assertSame(['a'], $this->stage->recipients(FollowTypes::ACTIVITY));
    }

    public function testAFailureOfTheQuietCallKeepsTheClaimsOfWhatWentOut(): void
    {
        $subject = Stage::subject('thread', '1');
        $this->follow('a', $subject);
        $this->follow('b', $subject);
        $this->stage->deliveries->markNotified($subject, ['b'], $this->stage->clock->now());
        $this->stage->notificationBus->failNext = new RuntimeException('The transport is down.');
        $this->stage->notificationBus->failAfter = 1;

        try {
            $this->stage->activities()->publish($subject, 'reply.created', id: 'r1');
            self::fail('The failure goes up, for the worker to retry.');
        } catch (RuntimeException) {
        }

        self::assertSame(['a'], $this->stage->connection->fetchFirstColumn('SELECT member FROM phil_follow_deliveries'));

        $this->stage->handler()(new FanOutActivity('r1'));
        self::assertSame(['a'], $this->stage->recipients(FollowTypes::ACTIVITY));
        self::assertSame(['b'], $this->stage->recipients(FollowTypes::QUIET));
    }

    public function testIgnoringASubjectWinsOverWatchingItsParent(): void
    {
        $thread = Stage::subject('thread', '1');
        $forum = Stage::subject('forum', '9');
        $this->stage->ports->parents['thread:1'] = [$forum];
        $this->follow('a', $forum);
        $this->follow('b', $forum);
        $this->follow('b', $thread, FollowLevel::Ignoring);

        $this->stage->activities()->publish($thread, 'reply.created');

        self::assertSame(['a'], $this->stage->recipients(FollowTypes::ACTIVITY));
    }

    public function testWatchingASubjectWinsOverIgnoringItsParent(): void
    {
        $thread = Stage::subject('thread', '1');
        $forum = Stage::subject('forum', '9');
        $category = Stage::subject('category', '3');
        $this->stage->ports->parents['thread:1'] = [$forum, $category];
        $this->follow('a', $category, FollowLevel::Ignoring);
        $this->follow('a', $thread);
        $this->follow('b', $category);
        $this->follow('b', $forum, FollowLevel::Ignoring);
        $this->follow('c', $category);

        $this->stage->activities()->publish($thread, 'reply.created');

        self::assertSame(['a', 'c'], $this->stage->recipients(FollowTypes::ACTIVITY));
    }

    public function testTheMentionsLevelHearsOnlyWhatMentionsTheMember(): void
    {
        $subject = Stage::subject('thread', '1');
        $this->follow('a', $subject, FollowLevel::Mentions);
        $this->follow('b', $subject, FollowLevel::Mentions);

        $this->stage->activities()->publish($subject, 'reply.created', mentions: ['b', 'x']);

        self::assertSame(['b'], $this->stage->recipients(FollowTypes::ACTIVITY));
    }

    public function testTheCustomLevelIsReadEventByEvent(): void
    {
        $subject = Stage::subject('thread', '1');
        $this->follow('a', $subject, FollowLevel::Custom, new EventPreferences(['reply.created' => FollowLevel::Watching, 'edit.made' => FollowLevel::Ignoring]));

        $activities = $this->stage->activities();
        $activities->publish($subject, 'reply.created', id: 'r1');
        $activities->publish($subject, 'edit.made', mentions: ['a'], id: 'r2');
        $activities->publish($subject, 'poll.opened', id: 'r3');
        $activities->publish($subject, 'poll.closed', mentions: ['a'], id: 'r4');

        self::assertSame(['r1', 'r4'], array_map(static fn (SendNotification $message): string => (string) $message->data['activity'], [...$this->stage->sent(FollowTypes::ACTIVITY), ...$this->stage->sent(FollowTypes::QUIET)]));
    }

    public function testOneNotificationGoesOutOnEveryChannelUntilTheMemberSeesTheSubject(): void
    {
        $subject = Stage::subject('thread', '1');
        $this->follow('a', $subject);
        $activities = $this->stage->activities($this->stage->handler(resendAfter: 3600));

        $activities->publish($subject, 'reply.created', id: 'r1');
        $activities->publish($subject, 'reply.created', id: 'r2');
        self::assertSame(['a'], $this->stage->recipients(FollowTypes::ACTIVITY));
        self::assertSame(['a'], $this->stage->recipients(FollowTypes::QUIET), 'The second stays in the centre.');

        $this->stage->follows()->seen('a', $subject);
        $activities->publish($subject, 'reply.created', id: 'r3');
        self::assertSame(['a', 'a'], $this->stage->recipients(FollowTypes::ACTIVITY), 'Seen, the next one goes out again.');

        $this->stage->clock->modify('+3601 seconds');
        $activities->publish($subject, 'reply.created', id: 'r4');
        self::assertSame(['a', 'a', 'a'], $this->stage->recipients(FollowTypes::ACTIVITY), 'Unseen past the delay, it goes out again.');
    }

    public function testTheDelayIncludesItsLastSecond(): void
    {
        $subject = Stage::subject('thread', '1');
        $this->follow('a', $subject);
        $activities = $this->stage->activities($this->stage->handler(resendAfter: 3600));

        $activities->publish($subject, 'reply.created', id: 'r1');

        $this->stage->clock->modify('+3600 seconds');
        $activities->publish($subject, 'reply.created', id: 'r2');

        self::assertSame(['a'], $this->stage->recipients(FollowTypes::QUIET));
    }

    public function testWithoutTheHoldEveryActivityGoesOutOnEveryChannel(): void
    {
        $subject = Stage::subject('thread', '1');
        $this->follow('a', $subject);
        $activities = $this->stage->activities($this->stage->handler(onceUntilSeen: false));

        $activities->publish($subject, 'reply.created', id: 'r1');
        $activities->publish($subject, 'reply.created', id: 'r2');

        self::assertSame(['a', 'a'], $this->stage->recipients(FollowTypes::ACTIVITY));
        self::assertSame(0, $this->stage->number('SELECT COUNT(*) FROM phil_follow_unseen'));
    }

    public function testAMemberWhoLostAccessHearsNothing(): void
    {
        $subject = Stage::subject('thread', '1');
        $this->follow('a', $subject);
        $this->follow('b', $subject);
        $this->stage->ports->refused = ['a'];

        $this->stage->activities()->publish($subject, 'reply.created');

        self::assertSame(['b'], $this->stage->recipients(FollowTypes::ACTIVITY));
    }

    public function testAPageWithoutRecipientsAsksNoAccessAndClaimsNothing(): void
    {
        $subject = Stage::subject('thread', '1');
        $this->follow('a', $subject, FollowLevel::Ignoring);

        $this->stage->activities()->publish($subject, 'reply.created');

        self::assertSame(0, $this->stage->ports->accessCalls);
        self::assertSame([], $this->stage->sent());
    }

    public function testAnActivityWithoutFollowersSendsNothing(): void
    {
        $this->stage->activities()->publish(Stage::subject('thread', '1'), 'reply.created');

        self::assertSame([], $this->stage->sent());
    }

    public function testAPurgedActivityIsSentToNoOne(): void
    {
        $this->follow('a', Stage::subject('thread', '1'));

        $this->stage->handler()(new FanOutActivity('gone'));

        self::assertSame([], $this->stage->sent());
    }

    public function testThePageSizeAndTheDelayAreBounded(): void
    {
        foreach ([0, 1001] as $size) {
            try {
                $this->stage->handler(batchSize: $size);
                self::fail('The page size '.$size.' is refused.');
            } catch (InvalidFollow $invalidFollow) {
                self::assertSame('A page of followers holds 1 to 1000 members.', $invalidFollow->getMessage());
            }
        }

        self::assertInstanceOf(FanOutActivityHandler::class, $this->stage->handler(batchSize: 1));
        self::assertInstanceOf(FanOutActivityHandler::class, $this->stage->handler(batchSize: 1000, resendAfter: 0));
        $this->expectException(InvalidFollow::class);
        $this->expectExceptionMessageIsOrContains('The delay before a notification goes out on every channel again is 0 seconds or more.');
        $this->stage->handler(resendAfter: -1);
    }

    public function testTheMessageCarriesIdentifiersOnly(): void
    {
        self::assertSame('m1', new FanOutActivity('a1', 'm1')->after);
        $this->expectException(InvalidFollow::class);
        new FanOutActivity('a1', 'not an identifier');
    }

    public function testTheMessageRefusesAnActivityOfTheWrongShape(): void
    {
        $this->expectException(InvalidFollow::class);
        new FanOutActivity('not an identifier');
    }

    private function follow(string $member, SubjectReference $subject, FollowLevel $level = FollowLevel::Watching, EventPreferences $events = new EventPreferences()): void
    {
        $this->stage->follows->save(new Follow($member, $subject, $level, $events, new DateTimeImmutable('2026-11-01 00:00:00 UTC')));
    }
}
