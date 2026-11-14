<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Application;

use Phil\NotificationsBundle\Application\Channels;
use Phil\NotificationsBundle\Application\Exception\UnknownNotificationType;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Channels::class)]
#[CoversClass(NotificationType::class)]
#[CoversClass(NotificationTypes::class)]
#[CoversClass(UnknownNotificationType::class)]
final class TypesTest extends TestCase
{
    #[TestWith(['site'])]
    #[TestWith(['email'])]
    #[TestWith(['web_push'])]
    #[TestWith(['chat/slack'])]
    #[TestWith(['chat/discord-events'])]
    public function testAChannelIsNamed(string $channel): void
    {
        self::assertSame($channel, Channels::check($channel));
    }

    #[TestWith([''])]
    #[TestWith(['Email'])]
    #[TestWith(['chat/'])]
    #[TestWith(['chat/slack/x'])]
    #[TestWith(['/slack'])]
    #[TestWith(["email\n"])]
    public function testAChannelOfAnotherShapeIsRefused(string $channel): void
    {
        $this->expectException(InvalidNotification::class);
        $this->expectExceptionMessageIsOrContains('is not the name of a channel, such as "site", "email" or "chat/slack".');

        Channels::check($channel);
    }

    public function testOnlyTheCentreAndEmailDoNotInterrupt(): void
    {
        self::assertFalse(Channels::interrupts('site'));
        self::assertFalse(Channels::interrupts('email'));
        self::assertTrue(Channels::interrupts('web_push'));
        self::assertTrue(Channels::interrupts('sms'));
    }

    public function testATypeHasDefaults(): void
    {
        $type = new NotificationType('comment.replied');

        self::assertSame('comment.replied', $type->key);
        self::assertSame(['site'], $type->channels);
        self::assertSame([], $type->broadcast);
        self::assertFalse($type->collapse);
        self::assertTrue($type->digest);
        self::assertTrue($type->toast);
        self::assertSame('medium', $type->importance);
        self::assertSame('comment.replied.title', $type->title);
        self::assertSame('comment.replied.body', $type->body);
    }

    public function testATypeIsConfigured(): void
    {
        $type = new NotificationType('event.created', ['site', 'site', 'email'], ['chat/discord', 'chat/discord', 'chat/slack'], true, false, false, 'urgent', 'a.title', 'a.body');

        self::assertSame(['site', 'email'], $type->channels);
        self::assertSame(['chat/discord', 'chat/slack'], $type->broadcast);
        self::assertTrue($type->collapse);
        self::assertFalse($type->digest);
        self::assertFalse($type->toast);
        self::assertSame('urgent', $type->importance);
        self::assertSame('a.title', $type->title);
        self::assertSame('a.body', $type->body);
    }

    public function testATypeChecksItsParts(): void
    {
        foreach ([
            'The notification type "Bad"' => static fn (): NotificationType => new NotificationType('Bad'),
            '"Email" is not the name' => static fn (): NotificationType => new NotificationType('a', ['Email']),
            '"chat/" is not the name' => static fn (): NotificationType => new NotificationType('a', [], ['chat/']),
            'The importance of "a" is one of "urgent", "high", "medium", "low".' => static fn (): NotificationType => new NotificationType('a', importance: 'loud'),
        ] as $message => $build) {
            try {
                $build();
                self::fail($message);
            } catch (InvalidNotification $invalidNotification) {
                self::assertStringStartsWith($message, $invalidNotification->getMessage());
            }
        }
    }

    public function testTheTypesAreFoundByKey(): void
    {
        $types = new NotificationTypes($a = new NotificationType('a'), $b = new NotificationType('b'), $again = new NotificationType('a', ['email']));

        self::assertSame($again, $types->get('a'));
        self::assertSame($b, $types->get('b'));
        self::assertTrue($types->has('a'));
        self::assertFalse($types->has('c'));
        self::assertSame([$again, $b], $types->all());
        self::assertNotSame($a, $types->get('a'));

        $this->expectException(UnknownNotificationType::class);
        $this->expectExceptionMessageIsOrContains('The notification type "c" is not declared under "phil_notifications.types".');

        $types->get('c');
    }
}
