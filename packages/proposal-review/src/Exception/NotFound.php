<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Exception;

use OutOfBoundsException;

/**
 * A campaign or a proposal the stores do not have.
 */
final class NotFound extends OutOfBoundsException implements ProposalReviewException
{
    public static function campaign(string $id): self
    {
        return new self(\sprintf('There is no campaign "%s".', $id));
    }

    public static function proposal(string $campaign, string $id): self
    {
        return new self(\sprintf('Campaign "%s" has no proposal "%s".', $campaign, $id));
    }
}
