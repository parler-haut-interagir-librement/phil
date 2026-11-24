<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Application\Contract;

use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\Participation;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;

/**
 * What the commerce or the ticketing of the application knows of a member's part in a subject: their purchase of the
 * product, their seat at the game or the event. It gives the mark « verified purchase » or « attended », and keeps the
 * reviews of a type reserved to its participants.
 */
interface Participations
{
    /**
     * The member's latest participation in the subject, or `null` when they took no part in it.
     */
    public function of(SubjectRef $subject, MemberId $member): ?Participation;
}
