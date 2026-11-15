<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\RemoteEvent;

use Override;
use Phil\BounceParser\BounceKind;
use Phil\NewsletterBundle\Application\Bounces\BounceHandler;
use Phil\NewsletterBundle\Application\Sending\MessageComposer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\RemoteEvent;

/**
 * The events a mail provider's webhook sends, as Symfony's Webhook component and the provider's Mailer bridge parse
 * them: a bounce or a dropped message is a hard bounce, a deferral a soft one, a spam report a complaint, an
 * unsubscription a request to leave. The delivery is found by the metadata the bundle set on the message.
 *
 * The application registers it as the consumer of its provider's webhook, with `#[AsRemoteEventConsumer]` or the tag
 * `remote_event.consumer`.
 */
final readonly class MailerEventConsumer implements ConsumerInterface
{
    public function __construct(
        private BounceHandler $bounces,
    ) {
    }

    #[Override]
    public function consume(RemoteEvent $event): void
    {
        if (!$event instanceof MailerDeliveryEvent && !$event instanceof MailerEngagementEvent) {
            return;
        }

        $kind = match ($event->getName()) {
            MailerDeliveryEvent::BOUNCE, MailerDeliveryEvent::DROPPED => BounceKind::Hard,
            MailerDeliveryEvent::DEFERRED => BounceKind::Soft,
            MailerEngagementEvent::SPAM => BounceKind::Complaint,
            MailerEngagementEvent::UNSUBSCRIBE => BounceKind::Feedback,
            default => null,
        };

        if (null === $kind) {
            return;
        }

        $delivery = $event->getMetadata()[MessageComposer::METADATA] ?? null;
        $this->bounces->handleEvent($kind, $event->getRecipientEmail(), \is_string($delivery) ? $delivery : null);
    }
}
