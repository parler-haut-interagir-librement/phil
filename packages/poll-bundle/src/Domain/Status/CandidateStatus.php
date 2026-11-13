<?php

declare(strict_types=1);

namespace Phil\PollBundle\Domain\Status;

/**
 * Where an option stands at the end of a round of a single transferable vote.
 */
enum CandidateStatus: string
{
    /** Still in the count, neither elected nor excluded. */
    case Hopeful = 'hopeful';

    /** Elected: it keeps the part of each ballot its keep factor says, and passes the rest on. */
    case Elected = 'elected';

    /** Excluded for holding the fewest votes: its ballots pass to their next choice. */
    case Excluded = 'excluded';

    /** Left out once every seat was filled. */
    case Defeated = 'defeated';
}
