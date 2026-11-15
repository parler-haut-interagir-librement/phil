<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application\Event;

use Phil\Lti\Platform\Ags\StoredLineItem;
use Phil\Lti\Service\Ags\Score;

/**
 * A score a tool sent the platform, kept as the learner's latest on the line item: the application's gradebook listens.
 */
final readonly class ScoreReceived
{
    public function __construct(
        public StoredLineItem $lineItem,
        public Score $score,
    ) {
    }
}
