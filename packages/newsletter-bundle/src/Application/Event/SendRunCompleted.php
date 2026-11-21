<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Event;

use DateTimeImmutable;

/**
 * Every message a send run queued was sent or skipped, once saved. It carries identifiers only.
 */
final readonly class SendRunCompleted
{
    public function __construct(
        public string $runId,
        public string $campaignId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
