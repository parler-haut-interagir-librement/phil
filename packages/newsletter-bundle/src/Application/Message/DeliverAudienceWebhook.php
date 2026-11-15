<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Message;

/**
 * Asks a worker to send one audience event to one configured receiver. It carries identifiers only.
 */
final readonly class DeliverAudienceWebhook
{
    public function __construct(
        public string $endpoint,
        public string $eventId,
        public string $type,
        public string $subscriberId,
        public string $site,
        public ?string $listId,
        public ?string $externalId,
        public int $occurredAt,
    ) {
    }
}
