<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Functional\App;

use Override;
use Phil\ReviewsBundle\Application\Contract\ResponderPolicy;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;

/**
 * The member `shop` responds to every review.
 */
final readonly class ShopResponds implements ResponderPolicy
{
    #[Override]
    public function mayRespond(SubjectRef $subject, MemberId $member): bool
    {
        return 'shop' === $member->value;
    }
}
