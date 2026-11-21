<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use LogicException;
use Phil\NotificationsBundle\Application\Delivery\ChannelOutcome;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Infrastructure\Notifier\NotifierChannelSender;
use Phil\NotificationsBundle\Tests\Unit\Fixture\NotifierTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionParameter;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Recipient\NoRecipient;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Transport\TransportInterface;

#[CoversClass(NotifierChannelSender::class)]
final class NotifierChannelSenderTest extends TestCase
{
    public function testItServesSmsAndPushByTheTexterAndChatByTheChatter(): void
    {
        $both = new NotifierChannelSender(new NotifierTransport(), new NotifierTransport());
        $texter = new NotifierChannelSender(new NotifierTransport());
        $chatter = new NotifierChannelSender(chatter: new NotifierTransport());

        foreach (['sms', 'sms/twilio', 'push', 'push/expo', 'chat', 'chat/slack'] as $channel) {
            self::assertTrue($both->supports($channel), $channel);
        }

        self::assertFalse($both->supports('email'));
        self::assertFalse($both->supports('web_push'));
        self::assertTrue($texter->supports('sms'));
        self::assertFalse($texter->supports('chat/slack'));
        self::assertFalse($chatter->supports('push'));
        self::assertTrue($chatter->supports('chat/slack'));
        self::assertFalse(new NotifierChannelSender()->supports('sms'));
    }

    public function testAnSmsGoesAtOnceToThePhoneOfTheRecipientOnItsTransport(): void
    {
        $transport = new NotifierTransport();
        $outcome = new NotifierChannelSender($transport)->send('sms/twilio', $this->rendered(), new NotificationType('comment.replied', importance: 'urgent'), new Recipient('', '+15550000042'));

        self::assertSame(ChannelOutcome::Accepted, $outcome);
        self::assertCount(1, $transport->sent);
        $message = $transport->sent[0];
        self::assertInstanceOf(SmsMessage::class, $message);
        self::assertSame('+15550000042', $message->getPhone());
        self::assertSame('Grace replied', $message->getSubject());
        self::assertSame('twilio', $message->getTransport());
    }

    public function testTheTransportKeepsWhatFollowsTheFirstSlashAndTheMessageTheChannel(): void
    {
        $transport = new NotifierTransport();
        new NotifierChannelSender($transport)->send('sms/twilio/eu', $this->rendered(), new NotificationType('comment.replied'), new Recipient('', '+15550000042'));
        $message = $transport->sent[0];

        self::assertInstanceOf(SmsMessage::class, $message);
        self::assertSame('twilio/eu', $message->getTransport());
        self::assertSame(['sms/twilio/eu'], $message->getNotification()?->getChannels(new NoRecipient()));
    }

    public function testAnSmsWithoutPhoneIsUnreachable(): void
    {
        $transport = new NotifierTransport();
        $sender = new NotifierChannelSender($transport);

        self::assertSame(ChannelOutcome::Unreachable, $sender->send('sms', $this->rendered(), new NotificationType('comment.replied'), new NoRecipient()));
        self::assertSame(ChannelOutcome::Unreachable, $sender->send('sms', $this->rendered(), new NotificationType('comment.replied'), null));
        self::assertSame([], $transport->sent);
    }

    public function testAPushAndAChatMessage(): void
    {
        $texts = new NotifierTransport();
        $chats = new NotifierTransport();
        $sender = new NotifierChannelSender($texts, $chats);

        self::assertSame(ChannelOutcome::Accepted, $sender->send('push', $this->rendered(), new NotificationType('comment.replied'), null));
        self::assertSame(ChannelOutcome::Accepted, $sender->send('chat/slack', $this->rendered(), new NotificationType('comment.replied'), null));
        self::assertSame(ChannelOutcome::Accepted, $sender->send('chat', $this->rendered(), new NotificationType('comment.replied'), new Recipient('ada@example.test')));

        $push = $texts->sent[0];
        self::assertInstanceOf(PushMessage::class, $push);
        self::assertSame('Grace replied', $push->getSubject());
        self::assertSame('On event 7', $push->getContent());
        self::assertNull($push->getTransport());
        [$slack, $chat] = $chats->sent;
        self::assertInstanceOf(ChatMessage::class, $slack);
        self::assertSame("Grace replied\n\nOn event 7", $slack->getSubject());
        self::assertSame('slack', $slack->getTransport());
        self::assertInstanceOf(ChatMessage::class, $chat);
        self::assertNull($chat->getTransport());
    }

    public function testARefusalOfTheTransportIsARefusal(): void
    {
        $transport = new NotifierTransport();
        $transport->failure = NotifierTransport::refusal();

        $sender = new NotifierChannelSender($transport, $transport);

        self::assertSame(ChannelOutcome::Refused, $sender->send('sms', $this->rendered(), new NotificationType('comment.replied'), new Recipient('', '+15550000042')));
        self::assertSame(ChannelOutcome::Refused, $sender->send('chat/slack', $this->rendered(), new NotificationType('comment.replied'), null));
    }

    public function testAnyOtherExceptionGoesUp(): void
    {
        $transport = new NotifierTransport();
        $transport->failure = new LogicException('Not a refusal.');

        $this->expectException(LogicException::class);

        new NotifierChannelSender($transport)->send('push', $this->rendered(), new NotificationType('comment.replied'), null);
    }

    /**
     * The sender is given the transports of the texter and the chatter, never the texter or the chatter, which put the
     * message — the phone number and the text — on a bus when `framework.notifier.message_bus` is set.
     */
    public function testTheSenderTakesTransportsNeverATexterOrAChatter(): void
    {
        $types = array_map(static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(), new ReflectionMethod(NotifierChannelSender::class, '__construct')->getParameters());

        self::assertSame(['?'.TransportInterface::class, '?'.TransportInterface::class], $types);
    }

    private function rendered(): RenderedNotification
    {
        return new RenderedNotification(new Notification('n1', 'comment.replied', '42', null, new DateTimeImmutable('2026-11-08 10:00 UTC')), 'Grace replied', 'On event 7');
    }
}
