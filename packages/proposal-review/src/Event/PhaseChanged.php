<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Event;

use Phil\ProposalReview\Phase;

/**
 * A campaign entered a new phase, as `ReviewBoard::advance()` saw it.
 */
final readonly class PhaseChanged
{
    public function __construct(
        public string $campaign,
        public Phase $from,
        public Phase $to,
    ) {
    }
}
