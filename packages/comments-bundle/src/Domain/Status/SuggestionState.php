<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Domain\Status;

/**
 * Where a suggested replacement stands: waiting for a decision, accepted — the passage replaced —, or rejected.
 */
enum SuggestionState: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
