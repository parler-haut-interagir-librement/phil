<?php

declare(strict_types=1);

namespace Phil\Listings;

/**
 * Where a listing stands in its life.
 */
enum ListingStatus: string
{
    /** Written by its author, seen by no one else. */
    case Draft = 'draft';

    /** Submitted, or edited after publication, and waiting for a moderator. */
    case PendingReview = 'pending_review';

    /** Shown and open to responses until it expires. */
    case Published = 'published';

    /** Turned down by a moderator; its author may edit it and submit it again. */
    case Rejected = 'rejected';

    /** Taken out of view by enough reports, until a moderator restores or removes it. */
    case Hidden = 'hidden';

    /** Past its expiry; its author may extend it. */
    case Expired = 'expired';

    /** Closed by its author: filled, found, no longer wanted. */
    case Closed = 'closed';

    /** Removed by a moderator, for good. */
    case Removed = 'removed';
}
