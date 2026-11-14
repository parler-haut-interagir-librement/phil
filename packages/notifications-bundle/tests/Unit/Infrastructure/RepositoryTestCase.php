<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\NotificationsBundle\Domain\Contract\NotificationRepository;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Domain\Status\NotificationState;
use Phil\NotificationsBundle\Domain\ValueObject\NotificationData;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use PHPUnit\Framework\TestCase;

/**
 * What every repository does, whatever keeps it.
 */
abstract class RepositoryTestCase extends TestCase
{
    abstract protected function repository(): NotificationRepository;

    public function testANotificationIsKeptAndFoundByItsRecipientOnly(): void
    {
        $repository = $this->repository();
        $kept = $repository->record($this->notification('n1', '42', '2026-11-08 10:00:00', subject: new SubjectReference('comment', '9'), actor: '17', data: ['event' => 7], origin: 'events'), true);
        $found = $repository->find('42', 'n1');

        self::assertSame('n1', $kept->id);
        self::assertInstanceOf(Notification::class, $found);
        self::assertSame('comment.replied', $found->type);
        self::assertSame('comment:9', $found->subject?->key());
        self::assertSame('17', $found->actor);
        self::assertSame(['event' => 7], $found->data->values);
        self::assertSame('events', $found->origin);
        self::assertSame(NotificationState::Unread, $found->state);
        self::assertSame(1, $found->count);
        self::assertSame('2026-11-08 10:00:00', $found->createdAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-08 10:00:00', $found->updatedAt->format('Y-m-d H:i:s'));
        self::assertNull($found->readAt);
        self::assertNull($repository->find('43', 'n1'));
        self::assertNull($repository->find('42', 'n2'));
    }

    public function testAnotherNotificationWithoutSubjectNorActor(): void
    {
        $repository = $this->repository();
        $repository->record($this->notification('n1', '42', '2026-11-08 10:00:00'), false);

        $found = $repository->find('42', 'n1');

        self::assertNull($found?->subject);
        self::assertNull($found?->actor);
        self::assertNull($found?->origin);
        self::assertSame([], $found?->data->values);
    }

    public function testASimilarUnreadNotificationCollapses(): void
    {
        $repository = $this->repository();
        $repository->record($this->notification('n1', '42', '2026-11-08 10:00:00', actor: '17', data: ['event' => 1]), true);
        $repository->record($this->notification('other-recipient', '43', '2026-11-08 10:30:00'), true);
        $repository->record($this->notification('other-subject', '42', '2026-11-08 10:30:00', subject: new SubjectReference('comment', '8')), true);

        $kept = $repository->record($this->notification('n2', '42', '2026-11-08 11:00:00', actor: '18', data: ['event' => 2]), true);
        $found = $repository->find('42', 'n1');

        self::assertSame('n1', $kept->id);
        self::assertSame(2, $kept->count);
        self::assertSame(2, $found?->count);
        self::assertSame('18', $found->actor);
        self::assertSame(['event' => 2], $found->data->values);
        self::assertSame('2026-11-08 11:00:00', $found->updatedAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-08 10:00:00', $found->createdAt->format('Y-m-d H:i:s'));
        self::assertNull($repository->find('42', 'n2'));
        self::assertSame(2, $repository->unreadCount('42'));
        self::assertSame(1, $repository->find('43', 'other-recipient')?->count);
        self::assertNull($repository->find('43', 'other-recipient')->actor);
    }

    public function testAReadNotificationDoesNotCollapse(): void
    {
        $repository = $this->repository();
        $repository->record($this->notification('n1', '42', '2026-11-08 10:00:00'), true);
        $repository->markRead('42', 'n1', new DateTimeImmutable('2026-11-08 10:30:00 UTC'));
        $repository->record($this->notification('n2', '42', '2026-11-08 11:00:00'), true);
        $repository->record($this->notification('n3', '42', '2026-11-08 12:00:00'), false);

        self::assertSame(2, $repository->unreadCount('42'));
        self::assertSame(1, $repository->find('42', 'n2')?->count);
    }

    public function testACollapsedNotificationComesBackToTheDigest(): void
    {
        $repository = $this->repository();
        $since = new DateTimeImmutable('2026-11-08 00:00:00 UTC');
        $repository->record($this->notification('n1', '42', '2026-11-08 10:00:00'), true);
        $repository->markDigested('42', ['n1'], new DateTimeImmutable('2026-11-08 10:30:00 UTC'));
        self::assertSame([], $repository->undigested('42', $since, 10));

        $repository->record($this->notification('n2', '42', '2026-11-08 11:00:00'), true);

        self::assertSame(['n1'], $this->ids($repository->undigested('42', $since, 10)));
    }

    public function testTheCentreListsTheLatestChangeFirstWithoutTheArchived(): void
    {
        $repository = $this->repository();
        $repository->record($this->notification('a', '42', '2026-11-08 10:00:00'), false);
        $repository->record($this->notification('c', '42', '2026-11-08 11:00:00'), false);
        $repository->record($this->notification('b', '42', '2026-11-08 11:00:00'), false);
        $repository->record($this->notification('d', '42', '2026-11-08 12:00:00'), false);
        $repository->record($this->notification('e', '43', '2026-11-08 13:00:00'), false);
        $repository->markRead('42', 'a', new DateTimeImmutable('2026-11-08 12:30:00 UTC'));
        $repository->archive('42', 'd');

        self::assertSame(['c', 'b', 'a'], $this->ids($repository->latest('42', 10)));
        self::assertSame(['c', 'b'], $this->ids($repository->latest('42', 2)));
        self::assertSame(NotificationState::Read, $repository->latest('42', 10)[2]->state);
    }

    public function testTiesAreListedByIdentifier(): void
    {
        $repository = $this->repository();

        foreach (['b', 'd', 'a', 'c'] as $id) {
            $repository->record($this->notification($id, '42', '2026-11-08 10:00:00'), false);
        }

        self::assertSame(['d', 'c', 'b', 'a'], $this->ids($repository->latest('42', 10)));
        self::assertSame(['a', 'b', 'c', 'd'], $this->ids($repository->undigested('42', new DateTimeImmutable('2026-11-08 00:00:00 UTC'), 10)));
    }

    public function testTheUnreadAreCountedAndMarkedRead(): void
    {
        $repository = $this->repository();
        $repository->record($this->notification('a', '42', '2026-11-08 10:00:00'), false);
        $repository->record($this->notification('b', '42', '2026-11-08 10:00:00'), false);
        $repository->record($this->notification('c', '42', '2026-11-08 10:00:00'), false);
        $repository->record($this->notification('d', '43', '2026-11-08 10:00:00'), false);

        $at = new DateTimeImmutable('2026-11-08 11:00:00 UTC');

        self::assertSame(3, $repository->unreadCount('42'));
        self::assertTrue($repository->markRead('42', 'a', $at));
        self::assertFalse($repository->markRead('42', 'a', $at));
        self::assertFalse($repository->markRead('43', 'b', $at));
        self::assertFalse($repository->markRead('42', 'missing', $at));
        self::assertSame('2026-11-08 11:00:00', $repository->find('42', 'a')?->readAt?->format('Y-m-d H:i:s'));
        self::assertSame(2, $repository->unreadCount('42'));
        self::assertSame(2, $repository->markAllRead('42', $at));
        self::assertSame(0, $repository->markAllRead('42', $at));
        self::assertSame(0, $repository->unreadCount('42'));
        self::assertSame(1, $repository->unreadCount('43'));
        self::assertSame(0, $repository->unreadCount('44'));
    }

    public function testANotificationIsArchivedOnce(): void
    {
        $repository = $this->repository();
        $repository->record($this->notification('a', '42', '2026-11-08 10:00:00'), false);

        self::assertFalse($repository->archive('43', 'a'));
        self::assertTrue($repository->archive('42', 'a'));
        self::assertFalse($repository->archive('42', 'a'));
        self::assertFalse($repository->archive('42', 'missing'));
        self::assertSame(NotificationState::Archived, $repository->find('42', 'a')?->state);
        self::assertSame(0, $repository->unreadCount('42'));
    }

    public function testTheDigestTakesTheUnreadOfTheWindowOldestFirst(): void
    {
        $repository = $this->repository();
        $repository->record($this->notification('old', '42', '2026-11-07 09:00:00'), false);
        $repository->record($this->notification('b', '42', '2026-11-07 12:00:00'), false);
        $repository->record($this->notification('a', '42', '2026-11-07 12:00:00'), false);
        $repository->record($this->notification('c', '42', '2026-11-08 08:00:00'), false);
        $repository->record($this->notification('read', '42', '2026-11-08 08:00:00'), false);
        $repository->record($this->notification('other', '43', '2026-11-08 08:00:00'), false);
        $repository->markRead('42', 'read', new DateTimeImmutable('2026-11-08 09:00:00 UTC'));

        $since = new DateTimeImmutable('2026-11-07 12:00:00 UTC');

        self::assertSame(['a', 'b', 'c'], $this->ids($repository->undigested('42', $since, 10)));
        self::assertSame(['a'], $this->ids($repository->undigested('42', $since, 1)));

        $repository->markDigested('42', ['a', 'other', 'missing'], new DateTimeImmutable('2026-11-08 09:00:00 UTC'));
        $repository->markDigested('42', [], new DateTimeImmutable('2026-11-08 09:00:00 UTC'));

        self::assertSame(['b', 'c'], $this->ids($repository->undigested('42', $since, 10)));
        self::assertSame(['other'], $this->ids($repository->undigested('43', $since, 10)));
    }

    public function testTheRecipientsAwaitingADigestComeAPageAtATime(): void
    {
        $repository = $this->repository();
        $repository->record($this->notification('a', '42', '2026-11-08 08:00:00'), false);
        $repository->record($this->notification('b', '42', '2026-11-08 09:00:00'), false);
        $repository->record($this->notification('c', '44', '2026-11-08 08:00:00'), false);
        $repository->record($this->notification('d', '43', '2026-11-08 08:00:00'), false);
        $repository->record($this->notification('e', '45', '2026-11-06 08:00:00'), false);
        $repository->record($this->notification('f', '46', '2026-11-08 08:00:00'), false);
        $repository->markRead('46', 'f', new DateTimeImmutable('2026-11-08 09:00:00 UTC'));
        $repository->record($this->notification('g', '47', '2026-11-08 08:00:00'), false);
        $repository->markDigested('47', ['g'], new DateTimeImmutable('2026-11-08 09:00:00 UTC'));

        $since = new DateTimeImmutable('2026-11-07 12:00:00 UTC');

        self::assertSame(['42', '43', '44'], $repository->awaitingDigest($since, null, 10));
        self::assertSame(['42', '43'], $repository->awaitingDigest($since, null, 2));
        self::assertSame(['44'], $repository->awaitingDigest($since, '43', 2));
        self::assertSame([], $repository->awaitingDigest($since, '44', 2));
    }

    public function testThePurgeDeletesWhatOutlivedItsRetention(): void
    {
        $repository = $this->repository();
        $repository->record($this->notification('unread-old', '42', '2026-01-01 00:00:00'), false);
        $repository->record($this->notification('unread-new', '42', '2026-06-01 00:00:00'), false);
        $repository->record($this->notification('read-old', '42', '2026-08-01 00:00:00'), false);
        $repository->record($this->notification('read-new', '42', '2026-10-01 00:00:00'), false);
        $repository->record($this->notification('archived-old', '42', '2026-08-01 00:00:00'), false);
        $repository->markRead('42', 'read-old', new DateTimeImmutable());
        $repository->markRead('42', 'read-new', new DateTimeImmutable());
        $repository->archive('42', 'archived-old');

        $readBefore = new DateTimeImmutable('2026-09-01 00:00:00 UTC');
        $unreadBefore = new DateTimeImmutable('2026-03-01 00:00:00 UTC');

        self::assertSame(2, $repository->purge($readBefore, $unreadBefore, 2));
        self::assertSame(1, $repository->purge($readBefore, $unreadBefore, 2));
        self::assertSame(0, $repository->purge($readBefore, $unreadBefore, 2));
        self::assertNull($repository->find('42', 'unread-old'));
        self::assertNull($repository->find('42', 'read-old'));
        self::assertNull($repository->find('42', 'archived-old'));
        self::assertNotNull($repository->find('42', 'unread-new'));
        self::assertNotNull($repository->find('42', 'read-new'));
    }

    public function testThePurgeKeepsWhatIsExactlyAtTheLimit(): void
    {
        $repository = $this->repository();
        $repository->record($this->notification('a', '42', '2026-03-01 00:00:00'), false);

        self::assertSame(0, $repository->purge(new DateTimeImmutable('2026-03-01 00:00:00 UTC'), new DateTimeImmutable('2026-03-01 00:00:00 UTC'), 10));
        self::assertSame(1, $repository->purge(new DateTimeImmutable('2026-03-01 00:00:01 UTC'), new DateTimeImmutable('2026-03-01 00:00:01 UTC'), 10));
    }

    /**
     * @param array<string, int|string> $data
     */
    protected function notification(string $id, string $recipient, string $at, ?SubjectReference $subject = null, ?string $actor = null, array $data = [], ?string $origin = null): Notification
    {
        return new Notification($id, 'comment.replied', $recipient, $subject, new DateTimeImmutable($at.' UTC'), $actor, NotificationData::from($data), $origin);
    }

    /**
     * @param list<Notification> $notifications
     *
     * @return list<string>
     */
    private function ids(array $notifications): array
    {
        return array_map(static fn (Notification $n): string => $n->id, $notifications);
    }
}
