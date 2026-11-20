<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Mailer;

use Override;
use Phil\NotificationsBundle\Application\Channels;
use Phil\NotificationsBundle\Application\Contract\ChannelSender;
use Phil\NotificationsBundle\Application\Delivery\ChannelOutcome;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * The `email` channel of the `first_success` mode: a plain text email, the title as its subject and its first line,
 * sent at once through the transports of Symfony Mailer — never through `MailerInterface`, which puts a
 * `SendEmailMessage` on the bus when Messenger routes it, with the address and the text, where a failure is only seen
 * later by a worker. An `urgent` type has the highest priority. A transport after the slash, "email/backup", is picked by the `X-Transport` header. The sender is
 * the configured address, or the one the mailer's `headers` give when none is configured.
 */
final readonly class MailerChannelSender implements ChannelSender
{
    public function __construct(
        private ?TransportInterface $transport = null,
        private ?string $from = null,
    ) {
    }

    #[Override]
    public function supports(string $channel): bool
    {
        return $this->transport instanceof TransportInterface && Channels::EMAIL === Channels::base($channel);
    }

    #[Override]
    public function send(string $channel, RenderedNotification $notification, NotificationType $type, ?RecipientInterface $contact): ChannelOutcome
    {
        if (!$contact instanceof EmailRecipientInterface || '' === $contact->getEmail() || !$this->transport instanceof TransportInterface) {
            return ChannelOutcome::Unreachable;
        }

        $email = new Email()
            ->to($contact->getEmail())
            ->subject($notification->title)
            ->text('' === $notification->body ? $notification->title : $notification->title."\n\n".$notification->body);

        if ('urgent' === $type->importance) {
            $email->priority(Email::PRIORITY_HIGHEST);
        }

        if (null !== $this->from) {
            $email->from($this->from);
        }

        $transport = explode('/', $channel, 2)[1] ?? null;

        if (null !== $transport) {
            $email->getHeaders()->addTextHeader('X-Transport', $transport);
        }

        try {
            $this->transport->send($email);
        } catch (TransportExceptionInterface) {
            return ChannelOutcome::Refused;
        }

        return ChannelOutcome::Accepted;
    }
}
