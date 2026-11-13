<?php

declare(strict_types=1);

namespace Phil\CommunityNotes;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * The verdict of one rater on one note, and when they gave it. A rater has one rating per note; rating again
 * replaces it.
 */
final readonly class Rating
{
    public function __construct(
        public string $note,
        public ResourceReference $rater,
        public Verdict $verdict,
        public DateTimeImmutable $ratedAt,
    ) {
    }
}
