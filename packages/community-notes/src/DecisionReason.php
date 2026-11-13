<?php

declare(strict_types=1);

namespace Phil\CommunityNotes;

/**
 * Why the rule decided what it did, as a stable code an application translates.
 */
enum DecisionReason: string
{
    /** Fewer ratings than the rule asks for. */
    case TooFewRatings = 'too_few_ratings';

    /** Neither verdict holds the share of the ratings the rule asks for. */
    case NoClearMajority = 'no_clear_majority';

    /** The verdict holds its share, but too few pairs of raters who usually disagree gave it together. */
    case NoCrossAgreement = 'no_cross_agreement';

    /** The verdict holds its share, and enough pairs of raters who usually disagree gave it together. */
    case CrossAgreement = 'cross_agreement';
}
