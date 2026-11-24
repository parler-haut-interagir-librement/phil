<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Model\Activity;
use Phil\FollowBundle\Domain\Model\Follow;
use Phil\FollowBundle\Domain\Model\FollowRequest;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\Status\RequestKind;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;
use Phil\FollowBundle\Domain\ValueObject\MemberPair;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalActivityRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalDeliveryRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalFollowerVisibilityRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalFollowRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalFollowRequestRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalMutualRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\Row;
use Phil\FollowBundle\Infrastructure\Doctrine\Sql;
use Phil\FollowBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * What the repositories send to the database — how many statements, which rows they reach, which types they bind —
 * where the result alone would not tell.
 */
#[CoversClass(DbalFollowRepository::class)]
#[CoversClass(DbalFollowRequestRepository::class)]
#[CoversClass(DbalMutualRepository::class)]
#[CoversClass(DbalActivityRepository::class)]
#[CoversClass(DbalDeliveryRepository::class)]
#[CoversClass(DbalFollowerVisibilityRepository::class)]
#[CoversClass(Row::class)]
#[CoversClass(Sql::class)]
final class StatementsAndBoundsTest extends TestCase
{
    private Stage $stage;

    protected function setUp(): void
    {
        $this->stage = new Stage();
    }

    public function testNothingIsSentForNoMembers(): void
    {
        $subject = Stage::subject('thread', '1');
        $now = $this->stage->clock->now();
        $this->stage->log->statements = 0;

        self::assertSame([], $this->stage->deliveries->claim('a1', [], $now));
        $this->stage->deliveries->release('a1', []);
        self::assertSame([], $this->stage->deliveries->unseen($subject, [], $now));
        $this->stage->deliveries->markNotified($subject, [], $now);

        self::assertSame(0, $this->stage->log->statements);
    }

    public function testNothingIsDeletedWhenNothingIsOldEnough(): void
    {
        $now = $this->stage->clock->now();
        $this->stage->log->statements = 0;

        self::assertSame(0, $this->stage->activities->purgeBefore($now, 10));
        self::assertSame(0, $this->stage->deliveries->purgeClaimsBefore($now, 10));
        self::assertSame(0, $this->stage->deliveries->purgeMarksBefore($now, 10));

        self::assertSame(3, $this->stage->log->statements, 'One read each.');
    }

    public function testThePurgeOfMarksTakesTheDistinctMembersThatAreOldEnough(): void
    {
        $old = new DateTimeImmutable('2026-10-01 00:00:00 UTC');
        $recent = new DateTimeImmutable('2026-11-21 11:00:00 UTC');

        foreach (['1', '2', '3'] as $id) {
            $this->stage->deliveries->markNotified(Stage::subject('thread', $id), ['b'], $old);
        }

        $this->stage->deliveries->markNotified(Stage::subject('thread', '1'), ['c'], $old);
        $this->stage->deliveries->markNotified(Stage::subject('thread', '1'), ['a'], $recent);

        self::assertSame(4, $this->stage->deliveries->purgeMarksBefore(new DateTimeImmutable('2026-11-21 10:00:00 UTC'), 2), 'Two members, whatever the number of their rows.');
        self::assertSame(['a'], $this->stage->connection->fetchFirstColumn('SELECT member FROM phil_follow_unseen'));
    }

    public function testThePurgeOfMarksLeavesTheRecentOnesOfTheFirstMembersAlone(): void
    {
        $this->stage->deliveries->markNotified(Stage::subject('thread', '1'), ['a'], new DateTimeImmutable('2026-11-21 11:00:00 UTC'));
        $this->stage->deliveries->markNotified(Stage::subject('thread', '1'), ['b'], new DateTimeImmutable('2026-10-01 00:00:00 UTC'));

        self::assertSame(1, $this->stage->deliveries->purgeMarksBefore(new DateTimeImmutable('2026-11-21 10:00:00 UTC'), 1));
        self::assertSame(['a'], $this->stage->connection->fetchFirstColumn('SELECT member FROM phil_follow_unseen'));
    }

    public function testErasingAMemberLeavesTheMarksAndClaimsOfTheOthers(): void
    {
        $subject = Stage::subject('thread', '1');
        $now = $this->stage->clock->now();
        $this->stage->deliveries->markNotified($subject, ['a', 'b'], $now);
        $this->stage->deliveries->claim('a1', ['a', 'b'], $now);

        $this->stage->deliveries->eraseMember('a');

        self::assertSame(['b'], $this->stage->connection->fetchFirstColumn('SELECT member FROM phil_follow_unseen'));
        self::assertSame(['b'], $this->stage->connection->fetchFirstColumn('SELECT member FROM phil_follow_deliveries'));
    }

    public function testASingleSubjectIsReadInOneStatement(): void
    {
        $thread = Stage::subject('thread', '1');
        $this->stage->follows->save(new Follow('a', $thread, FollowLevel::Watching, new EventPreferences(), $this->stage->clock->now()));
        $this->stage->log->statements = 0;

        self::assertCount(1, $this->stage->follows->candidates([$thread], null, 10));
        self::assertSame(1, $this->stage->log->statements);
        self::assertSame([], $this->stage->follows->candidates([], null, 10));

        $this->stage->log->statements = 0;
        self::assertSame([], $this->stage->follows->candidates([Stage::subject('thread', '2'), Stage::subject('forum', '1')], null, 10));
        self::assertSame(1, $this->stage->log->statements, 'No member to read the follows of: one statement.');
    }

    public function testErasingAMemberKeepsTheFollowsOfASubjectThatSharesTheirIdentifier(): void
    {
        $now = $this->stage->clock->now();
        $this->stage->follows->save(new Follow('q', Stage::subject('member', 'x'), FollowLevel::Watching, new EventPreferences(), $now));
        $this->stage->follows->save(new Follow('q', Stage::subject('thread', 'x'), FollowLevel::Watching, new EventPreferences(), $now));

        $this->stage->follows->eraseMember('x', Stage::subject('member', 'x'));

        self::assertSame(['thread'], $this->stage->connection->fetchFirstColumn('SELECT subject_type FROM phil_follows'));
    }

    public function testRemovingARequestRemovesThatRequestOnly(): void
    {
        $now = $this->stage->clock->now();
        $this->stage->requests->add(new FollowRequest('r1', 'a', Stage::subject('thread', '1'), RequestKind::Follow, 'o', FollowLevel::Watching, new EventPreferences(), $now));
        $this->stage->requests->add(new FollowRequest('r2', 'b', Stage::subject('thread', '1'), RequestKind::Follow, 'o', FollowLevel::Watching, new EventPreferences(), $now));

        self::assertTrue($this->stage->requests->remove('r1'));

        self::assertSame(['r2'], $this->stage->connection->fetchFirstColumn('SELECT id FROM phil_follow_requests'));
    }

    public function testChoosingToHideTouchesTheRowOfThatMemberOnly(): void
    {
        $this->stage->visibility->choose('a', false);
        $this->stage->visibility->choose('b', false);

        $this->stage->visibility->choose('a', true);

        self::assertTrue($this->stage->visibility->hidden('a'));
        self::assertFalse($this->stage->visibility->hidden('b'));
    }

    public function testTheChoiceToHideIsBoundAsABoolean(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturnOnConsecutiveCalls(false, ['followers_hidden' => 0]);
        $connection->method('convertToPHPValue')->willReturn(false);
        $connection->expects(self::once())->method('insert')->with('phil_follow_visibility', ['member' => 'a', 'followers_hidden' => true], ['followers_hidden' => Types::BOOLEAN]);
        $connection->expects(self::once())->method('update')->with('phil_follow_visibility', ['followers_hidden' => true], ['member' => 'a'], ['followers_hidden' => Types::BOOLEAN])->willReturn(1);

        $repository = new DbalFollowerVisibilityRepository($connection);
        $repository->choose('a', true);
        $repository->choose('a', true);
    }

    public function testErasingAMemberLeavesTheRelationshipsOfTheOthers(): void
    {
        $now = $this->stage->clock->now();
        $this->stage->mutuals->add(MemberPair::of('a', 'b'), $now);
        $this->stage->mutuals->add(MemberPair::of('b', 'c'), $now);
        $this->stage->mutuals->add(MemberPair::of('c', 'd'), $now);
        $this->stage->mutuals->add(MemberPair::of('a', 'e'), $now);

        $this->stage->mutuals->eraseMember('a');

        self::assertSame(2, $this->stage->number('SELECT COUNT(*) FROM phil_follow_mutuals'));
        self::assertTrue($this->stage->mutuals->exists(MemberPair::of('b', 'c')));
        self::assertTrue($this->stage->mutuals->exists(MemberPair::of('c', 'd')));
    }

    public function testErasingAMemberFindsTheirMentionsWithoutTheWildcardsOfTheirName(): void
    {
        $now = $this->stage->clock->now();
        $thread = Stage::subject('thread', '1');
        $this->stage->activities->record(new Activity('x1', $thread, 'reply.created', null, ['axb'], $now));
        $this->stage->activities->record(new Activity('x2', $thread, 'reply.created', null, ['a_b', 'c'], $now));
        $this->stage->log->statements = 0;

        $this->stage->activities->eraseMember('a_b');

        self::assertSame(3, $this->stage->log->statements, 'The actor rows, the read, the one update: the row of "axb" is not read.');
        self::assertSame(['axb'], $this->stage->activities->find('x1')?->mentions);
        self::assertSame(['c'], $this->stage->activities->find('x2')?->mentions);
    }

    public function testAnActivityReadsOnlyTheMentionsThatAreStrings(): void
    {
        $this->stage->connection->executeStatement("INSERT INTO phil_follow_activities (id, subject_type, subject_id, event, actor, mentions, occurred_at) VALUES ('x1', 'thread', '1', 'reply.created', NULL, '[1, \"a\", null]', '2026-11-01 00:00:00')");

        self::assertSame(['a'], $this->stage->activities->find('x1')?->mentions);
    }

    public function testAColumnThatHoldsNoBooleanIsRefused(): void
    {
        $row = new Row(['flag' => null], $this->stage->connection);

        $this->expectExceptionObject(InvalidFollow::because('The column "flag" holds a boolean.'));
        $row->bool('flag');
    }

    public function testRowsThatTheKeyAlreadyHoldsAreSkippedInOneStatementOnSqlite(): void
    {
        $this->stage->connection->executeStatement("INSERT INTO phil_follow_unseen (member, subject_type, subject_id, notified_at) VALUES ('a', 'thread', '1', '2026-11-01 00:00:00')");
        $this->stage->log->statements = 0;

        Sql::insertIgnoring($this->stage->connection, 'phil_follow_unseen', ['member', 'subject_type', 'subject_id', 'notified_at'], [
            ['a', 'thread', '1', new DateTimeImmutable('2026-11-02 00:00:00 UTC')],
            ['b', 'thread', '1', new DateTimeImmutable('2026-11-02 00:00:00 UTC')],
            ['c', 'thread', '1', new DateTimeImmutable('2026-11-02 00:00:00 UTC')],
        ], [3 => Types::DATETIME_UTC_IMMUTABLE]);

        self::assertSame(1, $this->stage->log->statements);
        self::assertSame(3, $this->stage->number('SELECT COUNT(*) FROM phil_follow_unseen'));
        self::assertSame('2026-11-01 00:00:00', $this->stage->connection->fetchOne("SELECT notified_at FROM phil_follow_unseen WHERE member = 'a'"));
    }

    public function testManyRowsAreInsertedInOneStatementWithOnePlaceholderEach(): void
    {
        $this->stage->log->statements = 0;

        Sql::insert($this->stage->connection, 'phil_follow_unseen', ['member', 'subject_type', 'subject_id', 'notified_at'], [
            ['a', 'thread', '1', new DateTimeImmutable('2026-11-02 00:00:00 UTC')],
            ['b', 'thread', '1', new DateTimeImmutable('2026-11-02 00:00:00 UTC')],
        ], [3 => Types::DATETIME_UTC_IMMUTABLE]);

        self::assertSame(1, $this->stage->log->statements);
        self::assertSame(2, $this->stage->number('SELECT COUNT(*) FROM phil_follow_unseen'));
    }
}
