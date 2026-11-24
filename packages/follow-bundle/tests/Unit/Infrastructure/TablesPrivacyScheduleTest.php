<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Phil\FollowBundle\Application\Message\PurgeFollowRecords;
use Phil\FollowBundle\Domain\Model\Activity;
use Phil\FollowBundle\Domain\Model\FollowRequest;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\Status\RequestKind;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;
use Phil\FollowBundle\Domain\ValueObject\MemberPair;
use Phil\FollowBundle\Infrastructure\Doctrine\FollowTables;
use Phil\FollowBundle\Infrastructure\Privacy\FollowPrivacy;
use Phil\FollowBundle\Infrastructure\Scheduler\FollowSchedule;
use Phil\FollowBundle\Tests\Fixtures\Stage;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

#[CoversClass(FollowTables::class)]
#[CoversClass(FollowPrivacy::class)]
#[CoversClass(FollowSchedule::class)]
final class TablesPrivacyScheduleTest extends TestCase
{
    public function testTheInstantsAreUtcColumnsAndTheKeysAreUnique(): void
    {
        $schema = FollowTables::configure(Schema::editor()->create());

        foreach (['phil_follows' => 'followed_at', 'phil_follow_requests' => 'requested_at', 'phil_follow_mutuals' => 'since', 'phil_follow_activities' => 'occurred_at', 'phil_follow_deliveries' => 'claimed_at', 'phil_follow_unseen' => 'notified_at'] as $table => $column) {
            self::assertSame(Types::DATETIME_UTC_IMMUTABLE, $schema->getTable($table)->getColumn($column)->getTypeName(), $table);
        }

        self::assertSame(FollowTables::ALL, array_map(static fn (Table $table): string => $table->getObjectName()->toString(), $schema->getTables()));
        self::assertSame(['member', 'subject_type', 'subject_id'], array_map(static fn (UnqualifiedName $name): string => $name->toString(), $schema->getTable('phil_follows')->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(IndexType::UNIQUE, $schema->getTable('phil_follow_requests')->getIndex('phil_follow_requests_pending')->getType());
        self::assertSame(IndexType::REGULAR, $schema->getTable('phil_follow_requests')->getIndex('phil_follow_requests_approver')->getType());
    }

    public function testTheIdentifiersCompareByteByByteOnMysql(): void
    {
        $mysql = FollowTables::configure(Schema::editor()->create(), new MySQLPlatform())->getTable('phil_follows');
        $postgresql = FollowTables::configure(Schema::editor()->create(), new PostgreSQLPlatform())->getTable('phil_follows');

        self::assertSame('utf8mb4_bin', $mysql->getColumn('member')->getCollation());
        self::assertSame('utf8mb4', $mysql->getColumn('member')->getCharset());
        self::assertNull($mysql->getColumn('level')->getCollation(), 'A level is no identifier.');
        self::assertNull($postgresql->getColumn('member')->getCollation());
    }

    public function testATableTheSchemaHasIsLeftAsItIs(): void
    {
        $schema = Schema::editor()->create();
        $schema = FollowTables::configure($schema);

        $again = FollowTables::configure($schema);

        self::assertCount(7, $again->getTables());
        self::assertEquals($schema->getTable('phil_follows'), $again->getTable('phil_follows'));
    }

    public function testTheExportHoldsTheMembersOwnRecords(): void
    {
        $stage = new Stage();
        $privacy = $this->privacy($stage);
        $member = DataSubject::fromString('a');
        self::assertSame([], $privacy->export($member));

        $stage->follows()->follow('a', Stage::subject('thread', '1'), FollowLevel::Custom, new EventPreferences(['reply.created' => FollowLevel::Watching]));
        $stage->follows()->follow('a', Stage::subject('forum', '1'), FollowLevel::Mentions);
        $stage->follows()->follow('z', Stage::subject('member', 'a'));
        $stage->requests->add(new FollowRequest('r1', 'a', Stage::subject('member', 'b'), RequestKind::Follow, 'b', FollowLevel::Watching, new EventPreferences(), new DateTimeImmutable('2026-11-20 00:00:00 UTC')));
        $stage->requests->add(new FollowRequest('r2', 'c', Stage::subject('member', 'a'), RequestKind::Mutual, 'a', FollowLevel::Watching, new EventPreferences(), new DateTimeImmutable('2026-11-20 00:00:00 UTC')));
        $stage->mutuals->add(MemberPair::of('a', 'd'), new DateTimeImmutable('2026-11-19 00:00:00 UTC'));
        $stage->visibility->choose('a', true);

        self::assertSame([
            'follows' => [
                ['subject' => 'forum:1', 'level' => 'mentions', 'events' => [], 'followed_at' => '2026-11-21T12:00:00+00:00'],
                ['subject' => 'thread:1', 'level' => 'custom', 'events' => ['reply.created' => 'watching'], 'followed_at' => '2026-11-21T12:00:00+00:00'],
            ],
            'requests_sent' => [['kind' => 'follow', 'requester' => 'a', 'subject' => 'member:b', 'approver' => 'b', 'requested_at' => '2026-11-20T00:00:00+00:00']],
            'requests_received' => [['kind' => 'mutual', 'requester' => 'c', 'subject' => 'member:a', 'approver' => 'a', 'requested_at' => '2026-11-20T00:00:00+00:00']],
            'mutual_relationships' => [['member' => 'd', 'since' => '2026-11-19T00:00:00+00:00']],
            'followers_hidden' => true,
        ], $privacy->export($member));
        self::assertSame('follows', $privacy->module()->value);
    }

    public function testTheExportReadsEveryPage(): void
    {
        $stage = new Stage();

        for ($i = 0; $i < 205; ++$i) {
            $stage->follows()->follow('a', Stage::subject('thread', (string) $i));
            $stage->requests->add(new FollowRequest(\sprintf('s%03d', $i), 'a', Stage::subject('group', (string) $i), RequestKind::Follow, 'o', FollowLevel::Watching, new EventPreferences(), new DateTimeImmutable()));
            $stage->requests->add(new FollowRequest(\sprintf('r%03d', $i), 'm'.$i, Stage::subject('member', 'a'), RequestKind::Follow, 'a', FollowLevel::Watching, new EventPreferences(), new DateTimeImmutable()));
        }

        $export = $this->privacy($stage)->export(DataSubject::fromString('a'));

        foreach (['follows', 'requests_sent', 'requests_received'] as $key) {
            self::assertIsArray($export[$key] ?? null);
            self::assertCount(205, $export[$key]);
        }
    }

    public function testTheErasureDeletesEverythingAndRunsTwice(): void
    {
        $stage = new Stage();
        $subject = Stage::subject('thread', '1');
        $stage->follows()->follow('a', $subject);
        $stage->follows()->follow('z', Stage::subject('member', 'a'));
        $stage->follows()->follow('z', $subject);
        $stage->mutuals->add(MemberPair::of('a', 'd'), $stage->clock->now());
        $stage->requests->add(new FollowRequest('r1', 'a', Stage::subject('member', 'b'), RequestKind::Follow, 'b', FollowLevel::Watching, new EventPreferences(), $stage->clock->now()));
        $stage->activities->record(new Activity('x', $subject, 'reply.created', 'a', [], $stage->clock->now()));
        $stage->activities->record(new Activity('y', $subject, 'reply.created', 'z', ['a'], $stage->clock->now()));
        $stage->deliveries->claim('y', ['a', 'z'], $stage->clock->now());
        $stage->deliveries->markNotified($subject, ['a'], $stage->clock->now());
        $stage->visibility->choose('a', true);

        $privacy = $this->privacy($stage);

        $report = $privacy->erase(DataSubject::fromString('a'));

        self::assertSame(array_fill_keys(FollowTables::ALL, ErasureEffect::Deleted), $report->effects);
        self::assertSame([], $privacy->export(DataSubject::fromString('a')));
        self::assertSame([['z', 'thread', '1']], $stage->connection->fetchAllNumeric('SELECT member, subject_type, subject_id FROM phil_follows'));
        self::assertSame([['y', '[]']], $stage->connection->fetchAllNumeric('SELECT id, mentions FROM phil_follow_activities'));
        self::assertSame(['z'], $stage->connection->fetchFirstColumn('SELECT member FROM phil_follow_deliveries'));
        self::assertSame(0, $stage->number('SELECT COUNT(*) FROM phil_follow_unseen'));
        self::assertEquals($report, $privacy->erase(DataSubject::fromString('a')));
    }

    public function testTheScheduleLocksAndKeepsItsState(): void
    {
        $schedule = new FollowSchedule('02:30', 'America/Toronto', new LockFactory(new InMemoryStore()), new ArrayAdapter())->getSchedule();
        $messages = $schedule->getRecurringMessages();

        self::assertCount(1, $messages);
        self::assertNotNull($schedule->getLock());
        self::assertNotNull($schedule->getState());
        $trigger = $messages[0]->getTrigger();
        self::assertInstanceOf(TriggerInterface::class, $trigger);
        // The first run is today at the configured time: a date after it gives the next day at that time.
        $noon = new DateTimeImmutable('tomorrow 12:00:00', new DateTimeZone('America/Toronto'));
        self::assertSame($noon->modify('+1 day')->setTime(2, 30)->format('Y-m-d H:i:s e'), $trigger->getNextRunDate($noon)?->format('Y-m-d H:i:s e'));
        $context = new MessageContext('phil_follow', 'id', $trigger, new DateTimeImmutable());
        self::assertEquals([new PurgeFollowRecords()], iterator_to_array($messages[0]->getMessages($context), false));
    }

    public function testTheScheduleRunsWithoutLockNorState(): void
    {
        $provider = new FollowSchedule();
        $schedule = $provider->getSchedule();

        self::assertNull($schedule->getLock());
        self::assertNull($schedule->getState());
        self::assertSame($schedule, $provider->getSchedule());
        self::assertSame('04:15', $schedule->getRecurringMessages()[0]->getTrigger()->getNextRunDate(new DateTimeImmutable('2026-11-21 12:00:00 UTC'))?->format('H:i'));
    }

    private function privacy(Stage $stage): FollowPrivacy
    {
        return new FollowPrivacy($stage->follows, $stage->requests, $stage->mutuals, $stage->activities, $stage->deliveries, $stage->visibility);
    }
}
