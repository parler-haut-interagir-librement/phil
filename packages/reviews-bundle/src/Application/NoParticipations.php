<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Application;

use Override;
use Phil\ReviewsBundle\Application\Contract\Participations;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\Participation;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;

/**
 * The participations of an application that gives none: no review is marked verified, and a type reserved to its
 * participants takes no review.
 */
final readonly class NoParticipations implements Participations
{
    #[Override]
    public function of(SubjectRef $subject, MemberId $member): ?Participation
    {
        return null;
    }
}
