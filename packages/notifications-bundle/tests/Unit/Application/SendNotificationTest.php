<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Application;

use Phil\NotificationsBundle\Application\Message\PurgeNotifications;
use Phil\NotificationsBundle\Application\Message\SendDigest;
use Phil\NotificationsBundle\Application\Message\SendDigests;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SendNotification::class)]
#[CoversClass(SendDigest::class)]
#[CoversClass(SendDigests::class)]
#[CoversClass(PurgeNotifications::class)]
final class SendNotificationTest extends TestCase
{
    public function testAMessageCarriesIdentifiers(): void
    {
        $message = new SendNotification('comment.replied', ['42', '42', '43'], null, 'comment', '9', ['ticket' => 'T', 'event' => 7], '17', 'events');

        self::assertSame('comment.replied', $message->type);
        self::assertSame(['42', '43'], $message->recipients);
        self::assertNull($message->audience);
        self::assertSame(['event' => 7, 'ticket' => 'T'], $message->data);
        self::assertSame('17', $message->actor);
        self::assertSame('events', $message->origin);
        self::assertTrue($message->broadcast);
        self::assertSame('comment:9', $message->subject()?->key());
        self::assertNull($message->dedupeKey);
    }

    public function testAMessageMayCarryAKeyOfDuplicatesThatEachRecipientKeeps(): void
    {
        $message = new SendNotification('comment.replied', ['42', '43'], dedupeKey: 'reminder:7');

        self::assertSame('reminder:7', $message->dedupeKey);
        self::assertSame('reminder:7', $message->for('43')->dedupeKey);
    }

    public function testAMessageMayHaveNoSubject(): void
    {
        $message = new SendNotification('welcome', audience: 'segment:4');

        self::assertNull($message->subject());
        self::assertSame('segment:4', $message->audience);
        self::assertSame([], $message->recipients);
    }

    public function testAMessageForOneRecipientDropsTheBroadcast(): void
    {
        $one = new SendNotification('comment.replied', ['42', '43'], 'segment:1', 'comment', '9', ['event' => 7], '17', 'events')->for('43');

        self::assertSame(['43'], $one->recipients);
        self::assertNull($one->audience);
        self::assertFalse($one->broadcast);
        self::assertSame('comment:9', $one->subject()?->key());
        self::assertSame(['event' => 7], $one->data);
        self::assertSame('17', $one->actor);
        self::assertSame('events', $one->origin);
        self::assertSame('comment.replied', $one->type);
        self::assertNull($one->dedupeKey);
    }

    public function testAThousandRecipientsAtMost(): void
    {
        $recipients = array_map(strval(...), range(1, 1000));
        self::assertCount(1000, new SendNotification('a', $recipients)->recipients);

        $this->expectExceptionMessageIsOrContains('A notification names 1000 recipients at most; send it to an audience instead.');

        new SendNotification('a', [...$recipients, '1001']);
    }

    public function testEveryPartIsChecked(): void
    {
        foreach ([
            'The notification type (length 1)' => static fn (): SendNotification => new SendNotification('A'),
            'The recipient (length 3)' => static fn (): SendNotification => new SendNotification('a', ['a b']),
            'The audience (length 3)' => static fn (): SendNotification => new SendNotification('a', audience: 'a b'),
            'The subject of a notification has both a type and an identifier.' => static fn (): SendNotification => new SendNotification('a', subjectType: 'comment'),
            'The subject of a notification has both a type and an identifier' => static fn (): SendNotification => new SendNotification('a', subjectId: '9'),
            'The subject type (length 1)' => static fn (): SendNotification => new SendNotification('a', subjectType: 'C', subjectId: '9'),
            'The data value of "x"' => static fn (): SendNotification => new SendNotification('a', data: ['x' => 'a b']),
            'The actor (length 3)' => static fn (): SendNotification => new SendNotification('a', actor: 'a b'),
            'The origin (length 1)' => static fn (): SendNotification => new SendNotification('a', origin: 'O'),
            'The deduplication key (length 16)' => static fn (): SendNotification => new SendNotification('a', dedupeKey: 'ada@example.test'),
        ] as $message => $build) {
            try {
                $build();
                self::fail($message);
            } catch (InvalidNotification $invalidNotification) {
                self::assertStringStartsWith($message, $invalidNotification->getMessage());
            }
        }
    }

    public function testTheScheduledMessagesCarryTheirFrequency(): void
    {
        self::assertSame(DigestFrequency::Weekly, new SendDigests(DigestFrequency::Weekly)->frequency);
        $digest = new SendDigest('42', DigestFrequency::Daily);
        self::assertSame('42', $digest->recipient);
        self::assertSame(DigestFrequency::Daily, $digest->frequency);
        self::assertInstanceOf(PurgeNotifications::class, new PurgeNotifications());
    }
}
