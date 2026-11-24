<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\FollowBundle\Application\Activities;
use Phil\FollowBundle\Application\FollowTypes;
use Phil\FollowBundle\Application\Handler\FanOutActivityHandler;
use Phil\FollowBundle\Application\Handler\PurgeFollowRecordsHandler;
use Phil\FollowBundle\Application\Message\FanOutActivity;
use Phil\FollowBundle\Domain\Contract\ActivityRepository;
use Phil\FollowBundle\Domain\Contract\DeliveryRepository;
use Phil\FollowBundle\Domain\Model\Activity;
use Phil\FollowBundle\Domain\Model\Follow;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;
use Phil\FollowBundle\Tests\Fixtures\RecordingBus;
use Phil\FollowBundle\Tests\Fixtures\Stage;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The handlers built with their defaults: the size of a page, the delay before a notification goes out on every channel
 * again, the retention, and where the loops of both stop.
 */
#[CoversClass(FanOutActivityHandler::class)]
#[CoversClass(PurgeFollowRecordsHandler::class)]
final class HandlerDefaultsTest extends TestCase
{
    private Stage $stage;

    protected function setUp(): void
    {
        $this->stage = new Stage();
    }

    public function testAPageOfFollowersHoldsFiveHundredByDefault(): void
    {
        $thread = Stage::subject('thread', '1');
        $this->stage->connection->executeStatement("WITH RECURSIVE c(i) AS (SELECT 1000 UNION ALL SELECT i + 1 FROM c WHERE i < 1500) INSERT INTO phil_follows (member, subject_type, subject_id, level, events, followed_at) SELECT 'm' || i, 'thread', '1', 'watching', '[]', '2026-11-01 00:00:00' FROM c");
        $bus = new RecordingBus();
        $handler = $this->defaultHandler($bus);

        $id = new Activities($this->stage->activities, $handler, $this->stage->clock)->publish($thread, 'reply.created');

        self::assertEquals([new FanOutActivity($id, 'm1499')], $bus->messages, 'The first page of 500 ends with the message of the next.');
        self::assertCount(500, $this->stage->recipients(FollowTypes::ACTIVITY));
    }

    public function testTheActivitiesAfterOneStayInTheCentreUntilTheSubjectIsSeenByDefault(): void
    {
        $thread = Stage::subject('thread', '1');
        $this->follow('a', $thread);
        $activities = new Activities($this->stage->activities, $this->defaultHandler(), $this->stage->clock);

        $activities->publish($thread, 'reply.created', 'z');
        $activities->publish($thread, 'reply.created', 'z');

        self::assertSame(['a'], $this->stage->recipients(FollowTypes::ACTIVITY));
        self::assertSame(['a'], $this->stage->recipients(FollowTypes::QUIET));
    }

    public function testTheCentreHoldsTheNextOnesForAWeekByDefault(): void
    {
        $held = Stage::subject('thread', '1');
        $due = Stage::subject('thread', '2');
        $this->follow('a', $held);
        $this->follow('a', $due);
        $now = $this->stage->clock->now();
        $this->stage->deliveries->markNotified($held, ['a'], $now->modify('-604800 seconds'));
        $this->stage->deliveries->markNotified($due, ['a'], $now->modify('-604801 seconds'));

        $activities = new Activities($this->stage->activities, $this->defaultHandler(), $this->stage->clock);

        $activities->publish($held, 'reply.created', 'z');
        self::assertSame(['a'], $this->stage->recipients(FollowTypes::QUIET), 'Notified a week ago to the second: still held.');
        self::assertSame([], $this->stage->recipients(FollowTypes::ACTIVITY));

        $activities->publish($due, 'reply.created', 'z');
        self::assertSame(['a'], $this->stage->recipients(FollowTypes::ACTIVITY), 'A second past the week: on every channel again.');
    }

    public function testTheMembersWhoHearOnEveryChannelAreAListOnceTheQuietOnesAreTakenOut(): void
    {
        $thread = Stage::subject('thread', '1');

        foreach (['a', 'b', 'c'] as $member) {
            $this->follow($member, $thread);
        }

        $this->stage->deliveries->markNotified($thread, ['b'], $this->stage->clock->now()->modify('-10 seconds'));
        new Activities($this->stage->activities, $this->defaultHandler(), $this->stage->clock)->publish($thread, 'reply.created', 'z');

        self::assertSame(['a', 'c'], $this->stage->sent(FollowTypes::ACTIVITY)[0]->recipients);
        self::assertSame(['b'], $this->stage->sent(FollowTypes::QUIET)[0]->recipients);
    }

    public function testAMemberWhoFollowsTheSubjectAndItsParentIsCountedOnceInAPage(): void
    {
        $thread = Stage::subject('thread', '1');
        $forum = Stage::subject('forum', '9');
        $this->stage->ports->parents['thread:1'] = [$forum];
        $this->follow('a', $thread);
        $this->follow('a', $forum);
        $this->follow('b', $forum);
        $bus = new RecordingBus();

        $handler = $this->stage->handler(batchSize: 3, bus: $bus);
        $id = $this->stage->activities($handler, $bus)->publish($thread, 'reply.created', 'z');
        $handler($bus->of(FanOutActivity::class)[0]);

        self::assertEquals([new FanOutActivity($id)], $bus->messages, 'Two members are not a full page of three.');
        self::assertSame(['a', 'b'], $this->stage->recipients(FollowTypes::ACTIVITY));
    }

    public function testTheCursorOfAFullPageIsItsLastMemberWhateverTheirFollows(): void
    {
        $thread = Stage::subject('thread', '1');
        $forum = Stage::subject('forum', '9');
        $this->stage->ports->parents['thread:1'] = [$forum];
        $this->follow('a', $thread);
        $this->follow('a', $forum);
        $this->follow('b', $forum);
        $this->follow('c', $forum);
        $bus = new RecordingBus();

        $handler = $this->stage->handler(batchSize: 2, bus: $bus);
        $id = $this->stage->activities($handler, $bus)->publish($thread, 'reply.created', 'z');
        $handler($bus->of(FanOutActivity::class)[0]);

        self::assertEquals(new FanOutActivity($id, 'b'), $bus->messages[1]);
    }

    public function testTheRetentionIsThirtyDaysAndAWeekByDefault(): void
    {
        $subject = Stage::subject('thread', '1');
        $now = $this->stage->clock->now();
        $this->stage->activities->record(new Activity('kept', $subject, 'reply.created', null, [], $now->modify('-30 days +1 second')));
        $this->stage->activities->record(new Activity('purged', $subject, 'reply.created', null, [], $now->modify('-30 days -1 second')));
        $this->stage->deliveries->markNotified(Stage::subject('thread', '2'), ['a'], $now->modify('-604801 seconds'));
        $this->stage->deliveries->markNotified(Stage::subject('thread', '3'), ['a'], $now->modify('-604800 seconds'));

        $deleted = new PurgeFollowRecordsHandler($this->stage->activities, $this->stage->deliveries, $this->stage->clock)();

        self::assertSame(2, $deleted);
        self::assertNotNull($this->stage->activities->find('kept'));
        self::assertNull($this->stage->activities->find('purged'));
        self::assertSame(['3'], $this->stage->connection->fetchFirstColumn('SELECT subject_id FROM phil_follow_unseen'));
    }

    public function testThePurgeStopsAfterAHundredFullBatchesAtMost(): void
    {
        $this->stage->connection->executeStatement("WITH RECURSIVE c(i) AS (SELECT 1 UNION ALL SELECT i + 1 FROM c WHERE i < 101500) INSERT INTO phil_follow_activities (id, subject_type, subject_id, event, actor, mentions, occurred_at) SELECT 'a' || i, 'thread', '1', 'reply.created', NULL, '[]', '2026-10-01 00:00:00' FROM c");

        $deleted = new PurgeFollowRecordsHandler($this->stage->activities, $this->stage->deliveries, $this->stage->clock)();

        self::assertSame(PurgeFollowRecordsHandler::BATCH * PurgeFollowRecordsHandler::BATCHES, $deleted);
        self::assertSame(1500, $this->stage->number('SELECT COUNT(*) FROM phil_follow_activities'));
    }

    public function testThePurgeStopsAtTheFirstBatchThatIsNotFull(): void
    {
        $activities = $this->createMock(ActivityRepository::class);
        $activities->expects(self::once())->method('purgeBefore')->willReturn(5);
        $deliveries = $this->createMock(DeliveryRepository::class);
        $deliveries->expects(self::once())->method('purgeClaimsBefore')->willReturn(0);
        $deliveries->expects(self::once())->method('purgeMarksBefore')->willReturn(0);

        self::assertSame(5, new PurgeFollowRecordsHandler($activities, $deliveries, $this->stage->clock)());
    }

    private function defaultHandler(?RecordingBus $bus = null): FanOutActivityHandler
    {
        return new FanOutActivityHandler($this->stage->activities, $this->stage->follows, $this->stage->deliveries, $this->stage->chain, $this->stage->ports, $this->stage->notifications, $this->stage->clock, bus: $bus);
    }

    private function follow(string $member, SubjectReference $subject): void
    {
        $this->stage->follows->save(new Follow($member, $subject, FollowLevel::Watching, new EventPreferences(), new DateTimeImmutable('2026-11-01 00:00:00 UTC')));
    }
}
