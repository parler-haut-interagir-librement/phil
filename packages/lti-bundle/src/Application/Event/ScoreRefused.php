<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application\Event;

use Phil\LtiBundle\Application\PendingScore;

/**
 * A score the platform refused for good — its platform no longer registered, or a refusal it will repeat —: kept as
 * refused, with its reason, until `phil:lti:purge` removes it; a listener keeps its own trace.
 */
final readonly class ScoreRefused
{
    public function __construct(
        public PendingScore $score,
    ) {
    }
}
