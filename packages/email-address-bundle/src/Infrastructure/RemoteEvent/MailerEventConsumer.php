<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Infrastructure\RemoteEvent;

use Override;
use Phil\EmailAddressBundle\Application\Bounces\BounceNotices;
use Phil\EmailAddressBundle\Application\Bounces\BounceRules;
use Phil\EmailAddressBundle\Domain\NoticeKind;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\RemoteEvent;

/**
 * The events of a mail provider's webhook, as the Webhook component and the provider's Mailer bridge parse them, sent
 * to the same bounce rules as the messages of the bounce mailbox: a bounce or a dropped message is hard, a deferral
 * soft, a spam report a complaint, an unsubscription a request to leave. The other events — delivered, opened,
 * clicked — give no notice.
 *
 * The Webhook component checked the provider's signature before the event got here: its complaints are authenticated.
 * The recipient identifier is read from the message metadata, under the key the configuration names.
 */
final readonly class MailerEventConsumer implements ConsumerInterface
{
    public function __construct(
        private BounceNotices $notices,
        private BounceRules $rules,
        private ?string $recipientMetadata = null,
    ) {
    }

    #[Override]
    public function consume(RemoteEvent $event): void
    {
        if (!$event instanceof MailerDeliveryEvent && !$event instanceof MailerEngagementEvent) {
            return;
        }

        $kind = match ($event->getName()) {
            MailerDeliveryEvent::BOUNCE, MailerDeliveryEvent::DROPPED => NoticeKind::Hard,
            MailerDeliveryEvent::DEFERRED => NoticeKind::Soft,
            MailerEngagementEvent::SPAM => NoticeKind::Complaint,
            MailerEngagementEvent::UNSUBSCRIBE => NoticeKind::Unsubscribe,
            default => null,
        };

        if (null === $kind) {
            return;
        }

        $recipientId = null === $this->recipientMetadata ? null : $event->getMetadata()[$this->recipientMetadata] ?? null;
        $this->rules->apply($this->notices->fromWebhook($kind, $event->getRecipientEmail(), $event->getId(), \is_string($recipientId) && '' !== $recipientId ? $recipientId : null));
    }
}
