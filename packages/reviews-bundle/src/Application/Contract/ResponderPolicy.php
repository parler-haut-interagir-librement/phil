<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Application\Contract;

use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;

/**
 * Who answers the reviews of a subject publicly: the merchant of the product, the organiser of the game or the event.
 */
interface ResponderPolicy
{
    public function mayRespond(SubjectRef $subject, MemberId $member): bool;
}
