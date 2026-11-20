<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use InvalidArgumentException;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Domain\Status\NotificationState;
use Phil\NotificationsBundle\Domain\ValueObject\NotificationData;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Notification::class)]
final class NotificationTest extends TestCase
{
    public function testANewNotificationIsUnreadAndCountsOne(): void
    {
        $at = new DateTimeImmutable('2026-11-08 10:00 UTC');
        $notification = new Notification('n1', 'comment.replied', '42', new SubjectReference('comment', '9'), $at, '17', NotificationData::from(['event' => 7]), 'events');

        self::assertSame('n1', $notification->id);
        self::assertSame('comment.replied', $notification->type);
        self::assertSame('42', $notification->recipient);
        self::assertSame('17', $notification->actor);
        self::assertSame(['event' => 7], $notification->data->values);
        self::assertSame('events', $notification->origin);
        self::assertSame(NotificationState::Unread, $notification->state);
        self::assertSame(1, $notification->count);
        self::assertSame($at, $notification->updatedAt);
        self::assertNull($notification->readAt);
        self::assertSame('comment.replied|comment:9', $notification->groupKey());
    }

    public function testTheOptionalPartsDefault(): void
    {
        $notification = new Notification('n1', 'welcome', '42', null, new DateTimeImmutable());

        self::assertNull($notification->actor);
        self::assertNull($notification->origin);
        self::assertSame([], $notification->data->values);
        self::assertSame('welcome|', $notification->groupKey());
    }

    public function testEveryPartIsChecked(): void
    {
        foreach ([
            'The notification identifier (length 3)' => static fn (): Notification => new Notification('a b', 't', '42', null, new DateTimeImmutable()),
            'The notification type (length 1)' => static fn (): Notification => new Notification('n', 'T', '42', null, new DateTimeImmutable()),
            'The recipient (length 3)' => static fn (): Notification => new Notification('n', 't', 'a@b', null, new DateTimeImmutable()),
            'The actor (length 3)' => static fn (): Notification => new Notification('n', 't', '42', null, new DateTimeImmutable(), 'a b'),
            'The origin (length 4)' => static fn (): Notification => new Notification('n', 't', '42', null, new DateTimeImmutable(), origin: 'Site'),
        ] as $message => $build) {
            try {
                $build();
                self::fail($message);
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertStringStartsWith($message, $invalidArgumentException->getMessage());
            }
        }
    }

    public function testALaterNotificationCollapsesIn(): void
    {
        $notification = new Notification('n1', 't', '42', null, new DateTimeImmutable('2026-11-08 10:00 UTC'), '17', NotificationData::from(['event' => 1]));
        $later = new Notification('n2', 't', '42', null, $at = new DateTimeImmutable('2026-11-08 11:00 UTC'), '18', NotificationData::from(['event' => 2]));

        $notification->collapse($later);

        self::assertSame(2, $notification->count);
        self::assertSame('18', $notification->actor);
        self::assertSame(['event' => 2], $notification->data->values);
        self::assertSame($at, $notification->updatedAt);
        self::assertSame('n1', $notification->id);
    }

    public function testItIsMarkedReadOnce(): void
    {
        $notification = new Notification('n1', 't', '42', null, new DateTimeImmutable());
        $at = new DateTimeImmutable('2026-11-08 12:00 UTC');

        self::assertTrue($notification->markRead($at));
        self::assertSame(NotificationState::Read, $notification->state);
        self::assertSame($at, $notification->readAt);
        self::assertFalse($notification->markRead(new DateTimeImmutable()));
        self::assertSame($at, $notification->readAt);
    }

    public function testItIsArchivedOnce(): void
    {
        $notification = new Notification('n1', 't', '42', null, new DateTimeImmutable());

        self::assertTrue($notification->archive());
        self::assertSame(NotificationState::Archived, $notification->state);
        self::assertFalse($notification->archive());
        self::assertFalse($notification->markRead(new DateTimeImmutable()));
    }

    public function testAStoredNotificationIsRestored(): void
    {
        $created = new DateTimeImmutable('2026-11-01 10:00 UTC');
        $updated = new DateTimeImmutable('2026-11-02 10:00 UTC');
        $read = new DateTimeImmutable('2026-11-03 10:00 UTC');
        $notification = Notification::restore('n1', 't', '42', null, $created, null, NotificationData::none(), null, NotificationState::Read, 3, $updated, $read);

        self::assertSame(NotificationState::Read, $notification->state);
        self::assertSame(3, $notification->count);
        self::assertSame($created, $notification->createdAt);
        self::assertSame($updated, $notification->updatedAt);
        self::assertSame($read, $notification->readAt);
        self::assertSame(1, Notification::restore('n1', 't', '42', null, $created, null, NotificationData::none(), null, NotificationState::Read, 1, $updated, null)->count);
    }

    public function testAStoredCountIsOneOrMore(): void
    {
        $this->expectExceptionMessageIsOrContains('The count of a stored notification is one or more.');

        Notification::restore('n1', 't', '42', null, new DateTimeImmutable(), null, NotificationData::none(), null, NotificationState::Read, 0, new DateTimeImmutable(), null);
    }
}
