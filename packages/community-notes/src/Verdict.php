<?php

declare(strict_types=1);

namespace Phil\CommunityNotes;

/**
 * What a rater says of a note: that it helps the readers of its subject, or that it does not.
 */
enum Verdict: string
{
    case Helpful = 'helpful';

    case NotHelpful = 'not_helpful';
}
