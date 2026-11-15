<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Webhook;

use Phil\NewsletterBundle\Application\Event\AudienceChanged;
use Phil\NewsletterBundle\Application\Message\DeliverAudienceWebhook;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Sends each audience event to every configured receiver that asked for its type, through Messenger, so that a slow
 * receiver never holds the request that changed the subscriber.
 */
final readonly class AudienceWebhookListener
{
    /**
     * @param array<string, list<string>> $endpoints the event types each receiver asked for, by its name; none for all
     */
    public function __construct(
        private MessageBusInterface $bus,
        private array $endpoints,
    ) {
    }

    public function __invoke(AudienceChanged $event): void
    {
        foreach ($this->endpoints as $name => $types) {
            if ([] !== $types && !\in_array($event->type->value, $types, true)) {
                continue;
            }

            $this->bus->dispatch(new DeliverAudienceWebhook($name, $event->id(), $event->type->value, $event->subscriberId, $event->site, $event->listId, $event->externalId, $event->occurredAt->getTimestamp()));
        }
    }
}
