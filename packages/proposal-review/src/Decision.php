<?php

declare(strict_types=1);

namespace Phil\ProposalReview;

/**
 * What the jury decides for a proposal.
 */
enum Decision: string
{
    case Accepted = 'accepted';

    /** Accepted if an accepted proposal withdraws. */
    case Waitlisted = 'waitlisted';

    case Rejected = 'rejected';
}
