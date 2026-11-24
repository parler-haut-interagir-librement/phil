<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Application\Contract;

use Phil\ReviewsBundle\Domain\ValueObject\MemberId;

/**
 * The name a review shows of its author.
 */
interface ReviewerDirectory
{
    public function displayName(MemberId $member): string;
}
