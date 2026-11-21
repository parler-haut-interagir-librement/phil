<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Event;

use DateTimeImmutable;

/**
 * An operator resumed a paused send run, once saved: what it held is queued again. It carries identifiers only.
 */
final readonly class SendRunResumed
{
    public function __construct(
        public string $runId,
        public string $campaignId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
