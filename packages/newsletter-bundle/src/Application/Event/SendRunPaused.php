<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Event;

use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Enum\PauseReason;

/**
 * A send run was paused, by its circuit breaker or by an operator, once saved: nothing more is sent until an operator
 * resumes it. It carries identifiers only.
 */
final readonly class SendRunPaused
{
    public function __construct(
        public string $runId,
        public string $campaignId,
        public PauseReason $reason,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
