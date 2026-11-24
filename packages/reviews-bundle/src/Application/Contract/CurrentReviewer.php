<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Application\Contract;

use Phil\ReviewsBundle\Domain\ValueObject\MemberId;

/**
 * Who is signed in, as the reviews name them; `null` for a visitor, who reads the reviews and writes none.
 */
interface CurrentReviewer
{
    public function member(): ?MemberId;
}
