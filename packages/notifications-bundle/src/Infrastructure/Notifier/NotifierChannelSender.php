<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Notifier;

use Override;
use Phil\NotificationsBundle\Application\Channels;
use Phil\NotificationsBundle\Application\Contract\ChannelSender;
use Phil\NotificationsBundle\Application\Delivery\ChannelOutcome;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\OutgoingNotification;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Recipient\NoRecipient;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;
use Symfony\Component\Notifier\TexterInterface;

/**
 * The `sms` and `push` channels through Symfony Notifier's texter, and the `chat` channels through its chatter, a
 * transport after the slash — "sms/twilio", "chat/slack" —, each message sent at once: the transport's answer, or its
 * `TransportExceptionInterface`, is known when `send()` returns. With `framework.notifier.message_bus` set, the texter
 * and the chatter put the message on the bus instead, where a failure is only seen later by a worker, and the message
 * waits there with the recipient's phone number and the text: keep it disabled for the `first_success` mode.
 */
final readonly class NotifierChannelSender implements ChannelSender
{
    public function __construct(
        private ?TexterInterface $texter = null,
        private ?ChatterInterface $chatter = null,
    ) {
    }

    #[Override]
    public function supports(string $channel): bool
    {
        return match (Channels::base($channel)) {
            Channels::SMS, 'push' => $this->texter instanceof TexterInterface,
            'chat' => $this->chatter instanceof ChatterInterface,
            default => false,
        };
    }

    #[Override]
    public function send(string $channel, RenderedNotification $notification, NotificationType $type, ?RecipientInterface $contact): ChannelOutcome
    {
        [$base, $transport] = explode('/', $channel, 2) + [1 => null];
        $outgoing = new OutgoingNotification($notification->title, [$channel])->content($notification->body)->importance($type->importance);

        $message = match ($base) {
            Channels::SMS => $contact instanceof SmsRecipientInterface ? SmsMessage::fromNotification($outgoing, $contact) : null,
            'push' => PushMessage::fromNotification($outgoing),
            default => $outgoing->asChatMessage($contact ?? new NoRecipient(), $transport),
        };

        if (!$message instanceof MessageInterface) {
            return ChannelOutcome::Unreachable;
        }

        if (null !== $transport) {
            $message->transport($transport);
        }

        try {
            if ('chat' === $base) {
                $this->chatter?->send($message);
            } else {
                $this->texter?->send($message);
            }
        } catch (TransportExceptionInterface) {
            return ChannelOutcome::Refused;
        }

        return ChannelOutcome::Accepted;
    }
}
