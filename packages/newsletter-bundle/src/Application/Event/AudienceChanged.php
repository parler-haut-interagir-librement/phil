<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Event;

use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Enum\AudienceEventType;

/**
 * Something happened to a subscriber: dispatched once it is saved, for listeners, automations and outgoing webhooks.
 * It carries identifiers only.
 */
final readonly class AudienceChanged
{
    /**
     * @param string|null $listId the list it happened on; `null` for the whole newsletter
     */
    public function __construct(
        public AudienceEventType $type,
        public string $subscriberId,
        public string $site,
        public ?string $listId,
        public ?string $externalId,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The same for every delivery of this event: drawn from what happened, to whom, where and when.
     */
    public function id(): string
    {
        return substr(hash('sha256', implode("\0", [$this->type->value, $this->subscriberId, $this->listId ?? '', (string) $this->occurredAt->getTimestamp()])), 0, 32);
    }
}
