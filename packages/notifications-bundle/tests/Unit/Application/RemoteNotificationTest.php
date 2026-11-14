<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Application;

use Phil\NotificationsBundle\Application\RemoteNotification;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RemoteNotification::class)]
final class RemoteNotificationTest extends TestCase
{
    public function testAPayloadIsRead(): void
    {
        $notification = RemoteNotification::fromPayload(['origin' => 'events', 'notification' => 'comment.replied', 'recipients' => ['42', '43'], 'subject' => ['type' => 'comment', 'id' => '9'], 'data' => ['event' => 7], 'actor' => '17']);

        self::assertSame('events', $notification->origin);
        self::assertSame('comment.replied', $notification->type);
        self::assertSame(['42', '43'], $notification->recipients);
        self::assertSame('comment:9', $notification->subject?->key());
        self::assertSame(['event' => 7], $notification->data->values);
        self::assertSame('17', $notification->actor);
    }

    public function testTheOptionalMembersMayBeLeftOut(): void
    {
        $notification = RemoteNotification::fromPayload(['origin' => 'events', 'notification' => 'welcome', 'recipients' => ['42'], 'actor' => null, 'subject' => null]);

        self::assertNull($notification->subject);
        self::assertNull($notification->actor);
        self::assertSame([], $notification->data->values);
    }

    public function testAThousandRecipients(): void
    {
        self::assertCount(1000, RemoteNotification::fromPayload(['origin' => 'a', 'notification' => 'b', 'recipients' => array_map(strval(...), range(1, 1000))])->recipients);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalid(): iterable
    {
        $valid = ['origin' => 'events', 'notification' => 'welcome', 'recipients' => ['42']];
        $list = 'A remote notification names from 1 to 1000 recipients in a list.';

        yield 'no recipients' => [['origin' => 'events', 'notification' => 'welcome'], $list];
        yield 'recipients not a list' => [[...$valid, 'recipients' => ['a' => '42']], $list];
        yield 'empty recipients' => [[...$valid, 'recipients' => []], $list];
        yield 'recipients a string' => [[...$valid, 'recipients' => '42'], $list];
        yield 'too many recipients' => [[...$valid, 'recipients' => array_map(strval(...), range(1, 1001))], $list];
        yield 'a recipient not a string' => [[...$valid, 'recipients' => [42]], 'The recipient "" is not an identifier'];
        yield 'a bad recipient' => [[...$valid, 'recipients' => ['a b']], 'The recipient "a b" is not an identifier'];
        yield 'subject a string' => [[...$valid, 'subject' => 'comment:9'], 'The subject of a remote notification is an object with a "type" and an "id".'];
        yield 'subject without id' => [[...$valid, 'subject' => ['type' => 'comment']], 'The member "id" of a remote notification is a string.'];
        yield 'subject without type' => [[...$valid, 'subject' => ['id' => '9']], 'The member "type" of a remote notification is a string.'];
        yield 'data a string' => [[...$valid, 'data' => 'x'], 'The data of a remote notification is an object.'];
        yield 'bad data' => [[...$valid, 'data' => ['x' => 1.5]], 'The data value of "x"'];
        yield 'no origin' => [['notification' => 'welcome', 'recipients' => ['42']], 'The member "origin" of a remote notification is a string.'];
        yield 'bad origin' => [[...$valid, 'origin' => 'Events'], 'The origin "Events" is not a key'];
        yield 'no type' => [['origin' => 'events', 'recipients' => ['42']], 'The member "notification" of a remote notification is a string.'];
        yield 'bad type' => [[...$valid, 'notification' => 'W'], 'The notification type "W" is not a key'];
        yield 'actor not a string' => [[...$valid, 'actor' => 17], 'The member "actor" of a remote notification is a string.'];
        yield 'bad actor' => [[...$valid, 'actor' => 'a b'], 'The actor "a b" is not an identifier'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalid')]
    public function testAPayloadOfTheWrongShapeIsRefused(array $payload, string $message): void
    {
        $this->expectException(InvalidNotification::class);
        $this->expectExceptionMessageIsOrContains($message);

        RemoteNotification::fromPayload($payload);
    }
}
