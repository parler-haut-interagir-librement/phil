<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Exception;

use DomainException;

/**
 * An action the campaign does not allow now or to this person: the wrong phase, a conflict of interest, a proposal the
 * reviewer is not assigned.
 */
final class Refused extends DomainException implements ProposalReviewException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
