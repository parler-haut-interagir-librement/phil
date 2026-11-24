<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
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
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The repositories on SQLite in memory, their tables created from their definition.
 */
#[CoversClass(DbalFollowRepository::class)]
#[CoversClass(DbalFollowRequestRepository::class)]
#[CoversClass(DbalMutualRepository::class)]
#[CoversClass(DbalActivityRepository::class)]
#[CoversClass(DbalDeliveryRepository::class)]
#[CoversClass(DbalFollowerVisibilityRepository::class)]
#[CoversClass(Row::class)]
#[CoversClass(Sql::class)]
final class RepositoriesTest extends TestCase
{
    private Stage $stage;

    protected function setUp(): void
    {
        $this->stage = new Stage();
    }

    public function testAFollowIsWrittenInUtcAndReadBack(): void
    {
        $subject = Stage::subject('thread', '1');
        $events = new EventPreferences(['reply.created' => FollowLevel::Watching]);
        $this->stage->follows->save(new Follow('a', $subject, FollowLevel::Custom, $events, new DateTimeImmutable('2026-11-21 07:00', new DateTimeZone('America/Toronto'))));

        self::assertSame(
            ['level' => 'custom', 'events' => '{"reply.created":"watching"}', 'followed_at' => '2026-11-21 12:00:00'],
            $this->stage->connection->fetchAssociative('SELECT level, events, followed_at FROM phil_follows'),
        );
        $follow = $this->stage->follows->find('a', $subject);
        self::assertSame('2026-11-21 12:00:00 UTC', $follow?->followedAt->format('Y-m-d H:i:s e'));
        self::assertSame($events->levels, $follow->events()->levels);
        self::assertNull($this->stage->follows->find('a', Stage::subject('thread', '2')));
        self::assertNull($this->stage->follows->find('b', $subject));
    }

    public function testSavingAKnownFollowChangesItsLevelOnly(): void
    {
        $subject = Stage::subject('thread', '1');
        $this->stage->follows->save(new Follow('a', $subject, FollowLevel::Watching, new EventPreferences(), new DateTimeImmutable('2026-11-01 00:00:00 UTC')));
        $this->stage->follows->save(new Follow('b', $subject, FollowLevel::Watching, new EventPreferences(), new DateTimeImmutable('2026-11-01 00:00:00 UTC')));
        $this->stage->follows->save(new Follow('a', $subject, FollowLevel::Mentions, new EventPreferences(), new DateTimeImmutable('2026-11-20 00:00:00 UTC')));

        self::assertSame(
            [['member' => 'a', 'level' => 'mentions', 'followed_at' => '2026-11-01 00:00:00'], ['member' => 'b', 'level' => 'watching', 'followed_at' => '2026-11-01 00:00:00']],
            $this->stage->connection->fetchAllAssociative('SELECT member, level, followed_at FROM phil_follows ORDER BY member'),
        );
        self::assertTrue($this->stage->follows->remove('a', $subject));
        self::assertFalse($this->stage->follows->remove('a', $subject));
        self::assertNotNull($this->stage->follows->find('b', $subject));
    }

    public function testTheCandidatesOfAChainAreThePagesMembersWithAllTheirFollows(): void
    {
        $thread = Stage::subject('thread', '1');
        $forum = Stage::subject('forum', '1');
        $other = Stage::subject('forum', '2');
        $this->save('a', $forum);
        $this->save('a', $thread, FollowLevel::Ignoring);
        $this->save('b', $other);
        $this->save('c', $thread);
        $this->save('d', $forum);
        $members = static fn (array $follows): array => array_map(static fn (Follow $follow): string => $follow->member.'@'.$follow->subject->key(), $follows);

        $page = $this->stage->follows->candidates([$thread, $forum], null, 2);
        self::assertSame(['a@forum:1', 'a@thread:1', 'c@thread:1'], $this->sorted($members($page)));
        self::assertSame(['d@forum:1'], $members($this->stage->follows->candidates([$thread, $forum], 'c', 2)));
        self::assertSame([], $this->stage->follows->candidates([$thread, $forum], 'd', 2));
        self::assertSame(['a@thread:1', 'c@thread:1'], $members($this->stage->follows->candidates([$thread], null, 5)));
        self::assertSame(['c@thread:1'], $members($this->stage->follows->candidates([$thread], 'a', 5)));
        self::assertSame([], $this->stage->follows->candidates([], null, 5));
    }

    public function testTheFollowersOfASubjectLeaveTheIgnoringOut(): void
    {
        $subject = Stage::subject('thread', '1');
        $this->save('b', $subject);
        $this->save('a', $subject, FollowLevel::Mentions);
        $this->save('c', $subject, FollowLevel::Ignoring);
        $this->save('d', Stage::subject('thread', '2'));

        self::assertSame(['a', 'b'], array_map(static fn (Follow $follow): string => $follow->member, $this->stage->follows->followersOf($subject, null, 10)));
        self::assertSame(['b'], array_map(static fn (Follow $follow): string => $follow->member, $this->stage->follows->followersOf($subject, 'a', 10)));
        self::assertSame(2, $this->stage->follows->countFollowers($subject));
        self::assertSame(0, $this->stage->follows->countFollowers(Stage::subject('thread', '3')));
    }

    public function testErasingAMemberDeletesTheirFollowsAndThoseOfThem(): void
    {
        $this->save('a', Stage::subject('thread', '1'));
        $this->save('b', Stage::subject('member', 'a'));
        $this->save('b', Stage::subject('member', 'c'));

        $this->stage->follows->eraseMember('a', Stage::subject('member', 'a'));

        self::assertSame([['b', 'c']], $this->stage->connection->fetchAllNumeric('SELECT member, subject_id FROM phil_follows'));
    }

    public function testARowThatIsNoFollowIsRefused(): void
    {
        $this->stage->connection->executeStatement("INSERT INTO phil_follows (member, subject_type, subject_id, level, events, followed_at) VALUES ('a', 'thread', '1', 'loud', '[]', '2026-11-01 00:00:00')");

        $this->expectExceptionObject(InvalidFollow::because('The level of a stored follow is "watching", "mentions", "ignoring" or "custom".'));
        $this->stage->follows->find('a', Stage::subject('thread', '1'));
    }

    public function testAColumnOfTheWrongShapeIsRefused(): void
    {
        $row = new Row(['s' => 1, 'j' => '"x"', 'b' => 'x', 'n' => null, 'd' => 'not a date'], $this->stage->connection);

        foreach ([
            'The column "s" holds a string.' => static fn (): mixed => $row->string('s'),
            'The column "missing" holds a string.' => static fn (): mixed => $row->string('missing'),
            'The column "j" holds a JSON object or list.' => static fn (): mixed => $row->json('j'),
        ] as $message => $read) {
            try {
                $read();
                self::fail($message);
            } catch (InvalidFollow $invalidFollow) {
                self::assertSame($message, $invalidFollow->getMessage());
            }
        }

        self::assertNull($row->nullableString('n'));
        self::assertNull($row->nullableString('missing'));
        self::assertSame(['a', '7'], Row::strings(['a', 7, null, 1.5]));
    }

    public function testARequestIsKeptOncePerRequesterSubjectAndKind(): void
    {
        $subject = Stage::subject('member', 'b');
        $first = $this->request('r1', 'a', $subject, RequestKind::Follow);

        self::assertTrue($this->stage->requests->add($first));
        self::assertFalse($this->stage->requests->add($this->request('r2', 'a', $subject, RequestKind::Follow)));
        self::assertTrue($this->stage->requests->add($this->request('r3', 'a', $subject, RequestKind::Mutual)));
        self::assertNull($this->stage->requests->find('r2'));

        $read = $this->stage->requests->find('r1');
        self::assertEquals($first, $read);
        self::assertSame('r1', $this->stage->requests->findPending('a', $subject, RequestKind::Follow)?->id);
        self::assertSame('r3', $this->stage->requests->findPending('a', $subject, RequestKind::Mutual)?->id);
        self::assertNull($this->stage->requests->findPending('c', $subject, RequestKind::Follow));
        self::assertNull($this->stage->requests->findPending('a', Stage::subject('member', 'x'), RequestKind::Follow));
    }

    public function testErasingAMemberDeletesTheRequestsOnBothSides(): void
    {
        $this->stage->requests->add($this->request('r1', 'a', Stage::subject('member', 'b'), RequestKind::Follow));
        $this->stage->requests->add($this->request('r2', 'c', Stage::subject('member', 'a'), RequestKind::Follow, 'a'));
        $this->stage->requests->add($this->request('r3', 'c', Stage::subject('member', 'd'), RequestKind::Follow, 'd'));

        $this->stage->requests->eraseMember('a');

        self::assertSame(['r3'], $this->stage->connection->fetchFirstColumn('SELECT id FROM phil_follow_requests'));
        self::assertTrue($this->stage->requests->remove('r3'));
        self::assertFalse($this->stage->requests->remove('r3'));
    }

    public function testARowThatIsNoRequestIsRefused(): void
    {
        $this->stage->requests->add($this->request('r1', 'a', Stage::subject('member', 'b'), RequestKind::Follow));

        foreach (['kind' => 'The kind of a stored request is "follow" or "mutual".', 'level' => 'The level of a stored request is "watching", "mentions", "ignoring" or "custom".'] as $column => $message) {
            $this->stage->connection->executeStatement(\sprintf("UPDATE phil_follow_requests SET %s = 'x'", $column));

            try {
                $this->stage->requests->find('r1');
                self::fail($message);
            } catch (InvalidFollow $invalidFollow) {
                self::assertSame($message, $invalidFollow->getMessage());
            }

            $this->stage->connection->executeStatement("UPDATE phil_follow_requests SET kind = 'follow', level = 'watching'");
        }
    }

    public function testARelationshipIsOnePairReadFromEitherSide(): void
    {
        $since = new DateTimeImmutable('2026-11-21 07:00', new DateTimeZone('America/Toronto'));

        self::assertTrue($this->stage->mutuals->add(MemberPair::of('b', 'a'), $since));
        self::assertFalse($this->stage->mutuals->add(MemberPair::of('a', 'b'), $since));
        self::assertTrue($this->stage->mutuals->exists(MemberPair::of('b', 'a')));
        self::assertSame([['a', 'b', '2026-11-21 12:00:00']], $this->stage->connection->fetchAllNumeric('SELECT * FROM phil_follow_mutuals'));
        self::assertEquals([['member' => 'b', 'since' => new DateTimeImmutable('2026-11-21 12:00:00 UTC')]], $this->stage->mutuals->exportOf('a'));
        self::assertEquals([['member' => 'a', 'since' => new DateTimeImmutable('2026-11-21 12:00:00 UTC')]], $this->stage->mutuals->exportOf('b'));
        self::assertSame([], $this->stage->mutuals->exportOf('c'));

        $this->stage->mutuals->add(MemberPair::of('b', 'c'), $since);
        $this->stage->mutuals->eraseMember('b');
        self::assertSame(0, $this->stage->number('SELECT COUNT(*) FROM phil_follow_mutuals'));
        self::assertFalse($this->stage->mutuals->remove(MemberPair::of('a', 'b')));
    }

    public function testTheOtherMembersAreMergedFromBothSidesInByteOrder(): void
    {
        foreach (['a', 'e', 'b', 'f'] as $other) {
            $this->stage->mutuals->add(MemberPair::of('c', $other), $this->stage->clock->now());
        }

        self::assertSame(['a', 'b', 'e', 'f'], $this->stage->mutuals->of('c', null, 10));
        self::assertSame(['a', 'b'], $this->stage->mutuals->of('c', null, 2));
        self::assertSame(['e', 'f'], $this->stage->mutuals->of('c', 'b', 2));
        self::assertSame(['c'], $this->stage->mutuals->of('a', null, 2));
        self::assertTrue($this->stage->mutuals->remove(MemberPair::of('c', 'a')));
        self::assertSame(['b'], $this->stage->mutuals->of('c', null, 1));
    }

    public function testAnActivityIsKeptOnceAndReadBack(): void
    {
        $activity = new Activity('a1', Stage::subject('thread', '1'), 'reply.created', 'z', ['b', 'c'], new DateTimeImmutable('2026-11-21 07:00', new DateTimeZone('America/Toronto')));

        self::assertTrue($this->stage->activities->record($activity));
        self::assertFalse($this->stage->activities->record(new Activity('a1', Stage::subject('thread', '2'), 'edit.made', null, [], new DateTimeImmutable())));
        self::assertSame(['subject_id' => '1', 'mentions' => '["b","c"]', 'occurred_at' => '2026-11-21 12:00:00'], $this->stage->connection->fetchAssociative('SELECT subject_id, mentions, occurred_at FROM phil_follow_activities'));
        self::assertEquals(new Activity('a1', Stage::subject('thread', '1'), 'reply.created', 'z', ['b', 'c'], new DateTimeImmutable('2026-11-21 12:00:00 UTC')), $this->stage->activities->find('a1'));
        self::assertNull($this->stage->activities->find('a2'));
    }

    public function testTheOldActivitiesArePurgedOldestFirstWithinTheLimit(): void
    {
        foreach (['x' => '2026-11-03', 'y' => '2026-11-01', 'z' => '2026-11-02', 'w' => '2026-11-10'] as $id => $day) {
            $this->stage->activities->record(new Activity($id, Stage::subject('thread', '1'), 'reply.created', null, [], new DateTimeImmutable($day.' 00:00:00 UTC')));
        }

        self::assertSame(2, $this->stage->activities->purgeBefore(new DateTimeImmutable('2026-11-10 00:00:00 UTC'), 2));
        self::assertSame(['w', 'x'], $this->stage->connection->fetchFirstColumn('SELECT id FROM phil_follow_activities ORDER BY id'));
        self::assertSame(1, $this->stage->activities->purgeBefore(new DateTimeImmutable('2026-11-10 00:00:00 UTC'), 2));
        self::assertSame(0, $this->stage->activities->purgeBefore(new DateTimeImmutable('2026-11-10 00:00:00 UTC'), 2));
    }

    public function testErasingAMemberDeletesTheirActivitiesAndTheirMentions(): void
    {
        $subject = Stage::subject('thread', '1');
        $this->stage->activities->record(new Activity('a1', $subject, 'reply.created', 'm_1', [], new DateTimeImmutable()));
        $this->stage->activities->record(new Activity('a2', $subject, 'reply.created', 'z', ['m_1', 'mx1', 'm_10'], new DateTimeImmutable()));
        $this->stage->activities->record(new Activity('a3', $subject, 'reply.created', 'z', ['mx1'], new DateTimeImmutable()));

        $this->stage->activities->eraseMember('m_1');

        self::assertSame([['a2', '["mx1","m_10"]'], ['a3', '["mx1"]']], $this->stage->connection->fetchAllNumeric('SELECT id, mentions FROM phil_follow_activities ORDER BY id'));
    }

    public function testErasingAMemberMentionedInManyActivitiesGoesThroughEveryPage(): void
    {
        for ($i = 0; $i < 1100; ++$i) {
            $this->stage->activities->record(new Activity(\sprintf('a%04d', $i), Stage::subject('thread', '1'), 'reply.created', null, ['m'], new DateTimeImmutable()));
        }

        $this->stage->activities->eraseMember('m');

        self::assertSame(0, $this->stage->number("SELECT COUNT(*) FROM phil_follow_activities WHERE mentions <> '[]'"));
    }

    public function testAClaimIsMadeOncePerActivityAndMember(): void
    {
        $at = new DateTimeImmutable('2026-11-21 12:00:00 UTC');

        self::assertSame(['b', 'a'], $this->stage->deliveries->claim('x', ['b', 'a'], $at));
        self::assertSame(['c'], $this->stage->deliveries->claim('x', ['a', 'c', 'b'], $at));
        self::assertSame(['a'], $this->stage->deliveries->claim('y', ['a'], $at));
        self::assertSame([], $this->stage->deliveries->claim('x', [], $at));

        $this->stage->deliveries->release('x', ['a', 'c']);
        $this->stage->deliveries->release('x', []);
        self::assertSame(['a', 'c'], $this->stage->deliveries->claim('x', ['a', 'b', 'c'], $at));
    }

    public function testTheMarksSayWhoWasNotifiedAndHasNotSeenTheSubject(): void
    {
        $subject = Stage::subject('thread', '1');
        $other = Stage::subject('thread', '2');
        $this->stage->deliveries->markNotified($subject, ['a', 'b'], new DateTimeImmutable('2026-11-21 10:00:00 UTC'));
        $this->stage->deliveries->markNotified($other, ['c'], new DateTimeImmutable('2026-11-21 10:00:00 UTC'));
        $this->stage->deliveries->markNotified($subject, ['b'], new DateTimeImmutable('2026-11-21 11:00:00 UTC'));
        $this->stage->deliveries->markNotified($subject, [], new DateTimeImmutable('2026-11-21 11:00:00 UTC'));

        self::assertSame(['b', 'a'], $this->stage->deliveries->unseen($subject, ['b', 'a', 'c'], new DateTimeImmutable('2026-11-21 10:00:00 UTC')));
        self::assertSame(['b'], $this->stage->deliveries->unseen($subject, ['a', 'b'], new DateTimeImmutable('2026-11-21 10:00:01 UTC')));
        self::assertSame([], $this->stage->deliveries->unseen($subject, [], new DateTimeImmutable('2026-11-21 00:00:00 UTC')));

        $this->stage->deliveries->seen('b', $subject);
        self::assertSame(['a'], $this->stage->deliveries->unseen($subject, ['a', 'b'], new DateTimeImmutable('2026-11-21 00:00:00 UTC')));
        self::assertSame(['c'], $this->stage->deliveries->unseen($other, ['c'], new DateTimeImmutable('2026-11-21 00:00:00 UTC')));
    }

    public function testTheClaimsAndMarksArePurgedAndErased(): void
    {
        $old = new DateTimeImmutable('2026-10-01 00:00:00 UTC');
        $now = new DateTimeImmutable('2026-11-21 00:00:00 UTC');
        $this->stage->deliveries->claim('x', ['a', 'b'], $old);
        $this->stage->deliveries->claim('y', ['a'], $old);
        $this->stage->deliveries->claim('z', ['a'], $now);
        $this->stage->deliveries->markNotified(Stage::subject('thread', '1'), ['a', 'b'], $old);
        $this->stage->deliveries->markNotified(Stage::subject('thread', '2'), ['a'], $now);

        $cutoff = new DateTimeImmutable('2026-11-01 00:00:00 UTC');

        self::assertSame(2, $this->stage->deliveries->purgeClaimsBefore($cutoff, 1), 'The claims of the first activity, all its members.');
        self::assertSame(1, $this->stage->deliveries->purgeClaimsBefore($cutoff, 1));
        self::assertSame(0, $this->stage->deliveries->purgeClaimsBefore($cutoff, 1));
        self::assertSame(1, $this->stage->deliveries->purgeMarksBefore($cutoff, 1));
        self::assertSame(1, $this->stage->deliveries->purgeMarksBefore($cutoff, 5));
        self::assertSame(0, $this->stage->deliveries->purgeMarksBefore($cutoff, 5));

        $this->stage->deliveries->claim('z', ['b'], $now);
        $this->stage->deliveries->eraseMember('a');
        self::assertSame(['b'], $this->stage->connection->fetchFirstColumn('SELECT member FROM phil_follow_deliveries'));
        self::assertSame(0, $this->stage->number('SELECT COUNT(*) FROM phil_follow_unseen'));
    }

    public function testTheVisibilityIsAChoiceKeptOnce(): void
    {
        self::assertNull($this->stage->visibility->hidden('a'));
        $this->stage->visibility->choose('a', true);
        self::assertTrue($this->stage->visibility->hidden('a'));
        $this->stage->visibility->choose('a', false);
        self::assertFalse($this->stage->visibility->hidden('a'));
        $this->stage->visibility->choose('b', true);
        self::assertSame(2, $this->stage->number('SELECT COUNT(*) FROM phil_follow_visibility'));

        $this->stage->visibility->eraseMember('a');
        self::assertNull($this->stage->visibility->hidden('a'));
        self::assertTrue($this->stage->visibility->hidden('b'));
    }

    public function testRowsAreInsertedTogetherAndJsonKeepsSlashesAndAccents(): void
    {
        Sql::insert($this->stage->connection, 'phil_follow_visibility', ['member', 'followers_hidden'], [['a', true], ['b', false]], [1 => 'boolean']);
        Sql::insert($this->stage->connection, 'phil_follow_visibility', ['member', 'followers_hidden'], []);

        self::assertSame([['a', 1], ['b', 0]], $this->stage->connection->fetchAllNumeric('SELECT member, followers_hidden FROM phil_follow_visibility ORDER BY member'));
        self::assertSame('{"a/b":"é"}', Sql::json(['a/b' => 'é']));
    }

    private function save(string $member, SubjectReference $subject, FollowLevel $level = FollowLevel::Watching): void
    {
        $this->stage->follows->save(new Follow($member, $subject, $level, new EventPreferences(), new DateTimeImmutable('2026-11-01 00:00:00 UTC')));
    }

    private function request(string $id, string $requester, SubjectReference $subject, RequestKind $kind, string $approver = 'b'): FollowRequest
    {
        return new FollowRequest($id, $requester, $subject, $kind, $approver, FollowLevel::Mentions, new EventPreferences(), new DateTimeImmutable('2026-11-21 12:00:00 UTC'));
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
