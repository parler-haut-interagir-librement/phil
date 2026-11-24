<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\Status;

/**
 * Where a review stands:
 *
 * - `Published`: shown, and counted in the ratings of its subject;
 * - `Withdrawn`: hidden from the public and left out of the ratings, kept for its author and the moderation. A review
 *   withdrawn for good names the published criterion it broke; one withdrawn without a criterion is held while the
 *   moderation looks at it.
 */
enum ReviewStatus: string
{
    case Published = 'published';
    case Withdrawn = 'withdrawn';
}
