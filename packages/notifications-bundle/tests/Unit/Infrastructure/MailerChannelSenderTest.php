<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use LogicException;
use Phil\NotificationsBundle\Application\Delivery\ChannelOutcome;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Infrastructure\Mailer\MailerChannelSender;
use Phil\NotificationsBundle\Tests\Unit\Fixture\MailerTransport;
use Phil\NotificationsBundle\Tests\Unit\Fixture\RecordingBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\Recipient\NoRecipient;
use Symfony\Component\Notifier\Recipient\Recipient;

#[CoversClass(MailerChannelSender::class)]
final class MailerChannelSenderTest extends TestCase
{
    public function testItServesEmailWhenThereIsATransport(): void
    {
        $sender = new MailerChannelSender(new MailerTransport());

        self::assertTrue($sender->supports('email'));
        self::assertTrue($sender->supports('email/backup'));
        self::assertFalse($sender->supports('sms'));
        self::assertFalse($sender->supports('emails'));
        self::assertFalse(new MailerChannelSender()->supports('email'));
    }

    public function testAPlainTextEmailGoesAtOnceToTheAddressOfTheRecipient(): void
    {
        $transport = new MailerTransport();
        $outcome = new MailerChannelSender($transport, 'notifications@example.test')->send('email', $this->rendered(), new NotificationType('comment.replied'), new Recipient('ada@example.test'));

        self::assertSame(ChannelOutcome::Accepted, $outcome);
        self::assertCount(1, $transport->sent);
        $email = $transport->sent[0];
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('ada@example.test', $email->getTo()[0]->getAddress());
        self::assertSame('notifications@example.test', $email->getFrom()[0]->getAddress());
        self::assertSame('Grace replied', $email->getSubject());
        self::assertSame("Grace replied\n\nOn event 7", $email->getTextBody());
        self::assertNull($email->getHtmlBody());
        self::assertSame(Email::PRIORITY_NORMAL, $email->getPriority());
        self::assertFalse($email->getHeaders()->has('X-Transport'));
    }

    public function testTheMailersOwnSenderATransportAndAnUrgentPriority(): void
    {
        $transport = new MailerTransport();
        $rendered = new RenderedNotification($this->rendered()->notification, 'Grace replied', '');
        new MailerChannelSender($transport)->send('email/backup', $rendered, new NotificationType('comment.replied', importance: 'urgent'), new Recipient('ada@example.test'));
        $email = $transport->sent[0];

        self::assertInstanceOf(Email::class, $email);
        self::assertSame([], $email->getFrom());
        self::assertSame('Grace replied', $email->getTextBody());
        self::assertSame(Email::PRIORITY_HIGHEST, $email->getPriority());
        self::assertSame('backup', $email->getHeaders()->get('X-Transport')?->getBodyAsString());
    }

    public function testARecipientWithoutAddressIsUnreachable(): void
    {
        $transport = new MailerTransport();
        $sender = new MailerChannelSender($transport);

        self::assertSame(ChannelOutcome::Unreachable, $sender->send('email', $this->rendered(), new NotificationType('comment.replied'), new NoRecipient()));
        self::assertSame(ChannelOutcome::Unreachable, $sender->send('email', $this->rendered(), new NotificationType('comment.replied'), new Recipient('', '+15550000042')));
        self::assertSame(ChannelOutcome::Unreachable, $sender->send('email', $this->rendered(), new NotificationType('comment.replied'), null));
        self::assertSame(ChannelOutcome::Unreachable, new MailerChannelSender()->send('email', $this->rendered(), new NotificationType('comment.replied'), new Recipient('ada@example.test')));
        self::assertSame([], $transport->sent);
    }

    public function testARefusalOfTheTransportIsARefusal(): void
    {
        self::assertSame(ChannelOutcome::Refused, new MailerChannelSender(MailerTransport::refusing())->send('email', $this->rendered(), new NotificationType('comment.replied'), new Recipient('ada@example.test')));
    }

    public function testAnyOtherExceptionGoesUp(): void
    {
        $transport = new MailerTransport();
        $transport->failure = new LogicException('Not a refusal.');

        $this->expectException(LogicException::class);

        new MailerChannelSender($transport)->send('email', $this->rendered(), new NotificationType('comment.replied'), new Recipient('ada@example.test'));
    }

    /**
     * Why the bundle sends through the transports and the documentation warns against routing `SendEmailMessage`:
     * through `MailerInterface` on a bus, the email waits in the queue with the address and the text.
     */
    public function testThroughTheMailerOnABusTheEmailWaitsWithTheAddressAndTheText(): void
    {
        $transport = new MailerTransport();
        $bus = new RecordingBus();
        new Mailer($transport, $bus)->send(new Email()->from('n@example.test')->to('ada@example.test')->subject('Grace replied')->text('On event 7'));

        self::assertSame([], $transport->sent);
        $queued = $bus->messages[0];
        self::assertInstanceOf(SendEmailMessage::class, $queued);
        $email = $queued->getMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('ada@example.test', $email->getTo()[0]->getAddress());
        self::assertSame('On event 7', $email->getTextBody());
    }

    private function rendered(): RenderedNotification
    {
        return new RenderedNotification(new Notification('n1', 'comment.replied', '42', null, new DateTimeImmutable('2026-11-08 10:00 UTC')), 'Grace replied', 'On event 7');
    }
}
