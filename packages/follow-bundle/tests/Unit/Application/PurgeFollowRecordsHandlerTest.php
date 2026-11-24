<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\FollowBundle\Application\Handler\PurgeFollowRecordsHandler;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Model\Activity;
use Phil\FollowBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PurgeFollowRecordsHandler::class)]
final class PurgeFollowRecordsHandlerTest extends TestCase
{
    public function testTheOldActivitiesClaimsAndMarksAreDeleted(): void
    {
        $stage = new Stage();
        $subject = Stage::subject('thread', '1');
        $old = new DateTimeImmutable('2026-10-01 00:00:00 UTC');
        $recent = new DateTimeImmutable('2026-11-20 00:00:00 UTC');
        $stage->activities->record(new Activity('old', $subject, 'reply.created', null, [], $old));
        $stage->activities->record(new Activity('new', $subject, 'reply.created', null, [], $recent));
        $stage->deliveries->claim('old', ['a', 'b'], $old);
        $stage->deliveries->claim('new', ['a'], $recent);
        $stage->deliveries->markNotified($subject, ['a'], new DateTimeImmutable('2026-11-21 10:59:59 UTC'));
        $stage->deliveries->markNotified(Stage::subject('thread', '2'), ['a'], new DateTimeImmutable('2026-11-21 11:00:00 UTC'));

        $handler = new PurgeFollowRecordsHandler($stage->activities, $stage->deliveries, $stage->clock, 30, 3600);
        $deleted = $handler();

        self::assertSame(4, $deleted);
        self::assertNull($stage->activities->find('old'));
        self::assertNotNull($stage->activities->find('new'));
        self::assertSame(['new'], $stage->connection->fetchFirstColumn('SELECT activity_id FROM phil_follow_deliveries'));
        self::assertSame(['2'], $stage->connection->fetchFirstColumn('SELECT subject_id FROM phil_follow_unseen'));
    }

    public function testThePurgeGoesOnWhileABatchIsFull(): void
    {
        $stage = new Stage();
        $old = new DateTimeImmutable('2026-10-01 00:00:00 UTC');
        $rows = [];

        for ($i = 0; $i < 2500; ++$i) {
            $rows[] = \sprintf("('a%d', 'thread', '1', 'reply.created', NULL, '[]', '2026-10-01 00:00:00')", $i);
        }

        $stage->connection->executeStatement('INSERT INTO phil_follow_activities (id, subject_type, subject_id, event, actor, mentions, occurred_at) VALUES '.implode(', ', $rows));
        $stage->deliveries->claim('a1', ['m'], $old);

        $handler = new PurgeFollowRecordsHandler($stage->activities, $stage->deliveries, $stage->clock);
        self::assertSame(2501, $handler());
        self::assertSame(0, $stage->number('SELECT COUNT(*) FROM phil_follow_activities'));
    }

    public function testThePeriodsAreBounded(): void
    {
        $stage = new Stage();
        self::assertInstanceOf(PurgeFollowRecordsHandler::class, new PurgeFollowRecordsHandler($stage->activities, $stage->deliveries, $stage->clock, 1, 0));

        try {
            new PurgeFollowRecordsHandler($stage->activities, $stage->deliveries, $stage->clock, 0);
            self::fail('Refused.');
        } catch (InvalidFollow $invalidFollow) {
            self::assertSame('Activities are kept 1 day or more.', $invalidFollow->getMessage());
        }

        $this->expectExceptionObject(InvalidFollow::because('The delay before a notification goes out on every channel again is 0 seconds or more.'));
        new PurgeFollowRecordsHandler($stage->activities, $stage->deliveries, $stage->clock, 30, -1);
    }
}
