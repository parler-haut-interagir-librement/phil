<?php

declare(strict_types=1);

namespace Phil\Assignment;

/**
 * Why a ranked choice did not become the placement of an unplaced applicant.
 */
enum ChoiceOutcome: string
{
    /** The slot had no seat left for the applicant when the choice was tried; for a group, not enough seats. */
    case Full = 'full';

    /** The organiser forbade the applicant this slot. */
    case Forbidden = 'forbidden';

    /** The slot was closed for ending under its minimum. */
    case Closed = 'closed';
}
