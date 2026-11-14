<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Read;

/**
 * What the verification of a segment says, for a person to read.
 */
enum ChainState: string
{
    /** Not verified yet: verification reads every row of the segment, so it is asked for. */
    case Unverified = 'unverified';

    /** Every row, link and anchor holds. */
    case Holds = 'holds';

    /** It holds, with breaks a covered discontinuity explains, to review. */
    case HoldsWithExplainedBreaks = 'explained';

    /** At least one alarm: the trail does not hold. */
    case Broken = 'broken';
}
