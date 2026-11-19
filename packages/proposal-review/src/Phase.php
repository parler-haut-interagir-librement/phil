<?php

declare(strict_types=1);

namespace Phil\ProposalReview;

/**
 * Where a campaign stands. Its dated schedule decides the phase from the clock; each phase opens some actions and
 * closes others.
 */
enum Phase: string
{
    /** Before the first window of the schedule: nothing is open. */
    case NotStarted = 'not-started';

    /** Proposals are entered. */
    case Submission = 'submission';

    /** Reviewers score the proposals they may review; votes between pairs are cast. */
    case Review = 'review';

    /** Authors answer the reviews of their proposals, if the schedule has this window. */
    case AuthorResponse = 'author-response';

    /** The jury records its decisions, then applies them together. */
    case Deliberation = 'deliberation';

    /** After the last window: decisions may still be applied, nothing else changes. */
    case Closed = 'closed';

    /**
     * The position of the phase in a campaign's life, to compare two phases.
     */
    public function position(): int
    {
        return match ($this) {
            self::NotStarted => 0,
            self::Submission => 1,
            self::Review => 2,
            self::AuthorResponse => 3,
            self::Deliberation => 4,
            self::Closed => 5,
        };
    }
}
