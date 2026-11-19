<?php

declare(strict_types=1);

namespace Phil\ProposalReview;

/**
 * How the scores of several reviews make the score of a proposal.
 */
enum Aggregation: string
{
    /** The average: every review counts. */
    case Mean = 'mean';

    /** The middle value, or the average of the two middle values: one harsh or generous reviewer moves it little. */
    case Median = 'median';
}
