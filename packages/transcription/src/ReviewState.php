<?php

declare(strict_types=1);

namespace Phil\Transcription;

/**
 * Whether a person read a transcript against its recording: a machine's transcript is to review until then, and is not
 * published.
 */
enum ReviewState: string
{
    case ToReview = 'to_review';
    case Reviewed = 'reviewed';
}
