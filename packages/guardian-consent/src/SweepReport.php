<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

/**
 * What one sweep did: the requests erased unanswered, and the members told of the handover.
 */
final readonly class SweepReport
{
    public function __construct(
        public int $erasedRequests,
        public int $handoverNotices,
    ) {
    }
}
