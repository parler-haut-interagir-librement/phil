<?php

declare(strict_types=1);

namespace Phil\PollBundle\Domain\Counting;

use Phil\PollBundle\Domain\Status\CandidateStatus;

/**
 * One option in a round of a single transferable vote: the votes it held in the round's last pass over the ballots,
 * the keep factor that pass used, and where it stands at the end of the round.
 */
final readonly class CandidateTally
{
    public function __construct(
        public string $option,
        public Billionths $votes,
        public Billionths $keepFactor,
        public CandidateStatus $status,
    ) {
    }
}
