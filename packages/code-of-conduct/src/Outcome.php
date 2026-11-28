<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct;

/**
 * What the responsible people decided on a report, from the lightest to the heaviest. The package records it; the
 * application applies it, through its moderation, from the `ReportStatusChanged` event.
 */
enum Outcome: string
{
    /** Nothing breached the code, or nothing could be established. */
    case NoAction = 'no_action';

    /** A private word with the person, asking for a change of behaviour. */
    case Correction = 'correction';

    /** A formal warning, with what happens if it recurs. */
    case Warning = 'warning';

    /** An exclusion for a time: from the event, the space, or the community. */
    case TemporaryExclusion = 'temporary_exclusion';

    /** An exclusion for good. */
    case PermanentExclusion = 'permanent_exclusion';
}
