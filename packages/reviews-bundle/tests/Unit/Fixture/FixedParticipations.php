<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Unit\Fixture;

use Override;
use Phil\ReviewsBundle\Application\Contract\Participations;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\Participation;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;

/**
 * The participations a test sets, by subject and member.
 */
final class FixedParticipations implements Participations
{
    /** @var array<string, Participation> */
    private array $participations = [];

    public function set(SubjectRef $subject, MemberId $member, Participation $participation): void
    {
        $this->participations[$subject.'|'.$member->value] = $participation;
    }

    #[Override]
    public function of(SubjectRef $subject, MemberId $member): ?Participation
    {
        return $this->participations[$subject.'|'.$member->value] ?? null;
    }
}
