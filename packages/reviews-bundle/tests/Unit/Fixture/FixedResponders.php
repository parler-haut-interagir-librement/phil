<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Unit\Fixture;

use Override;
use Phil\ReviewsBundle\Application\Contract\ResponderPolicy;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;

/**
 * The members a test lets respond, to every subject.
 */
final class FixedResponders implements ResponderPolicy
{
    /** @var list<string> */
    public array $members = [];

    #[Override]
    public function mayRespond(SubjectRef $subject, MemberId $member): bool
    {
        return \in_array($member->value, $this->members, true);
    }
}
