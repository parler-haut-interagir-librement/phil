<?php

declare(strict_types=1);

namespace Phil\CommunityNotes;

/**
 * Where a note stands. Only a helpful note is shown beside its subject; the others are shown to the raters.
 */
enum NoteStatus: string
{
    /** Not decided yet: too few ratings, or no agreement across raters who usually disagree. */
    case NeedsMoreRatings = 'needs_more_ratings';

    /** Rated helpful by raters who usually disagree: published beside its subject. */
    case Helpful = 'helpful';

    /** Rated not helpful by raters who usually disagree. */
    case NotHelpful = 'not_helpful';

    /** Withdrawn by its author: neither shown nor rated any more. */
    case Withdrawn = 'withdrawn';
}
