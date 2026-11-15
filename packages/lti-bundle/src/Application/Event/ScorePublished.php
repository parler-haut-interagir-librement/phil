<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application\Event;

use Phil\LtiBundle\Application\PendingScore;

/**
 * A score the tool sent, taken by the platform.
 */
final readonly class ScorePublished
{
    public function __construct(
        public PendingScore $score,
    ) {
    }
}
