<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo;

/**
 * Why a reading of someone else's safety information was allowed.
 */
enum AccessReason: string
{
    /** A responsible person of the event, during the access window. */
    case Responsible = 'responsible';

    /** A member of the team who broke the glass in an emergency, with a written reason. */
    case BreakGlass = 'break_glass';
}
