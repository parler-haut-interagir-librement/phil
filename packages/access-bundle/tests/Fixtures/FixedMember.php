<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Fixtures;

use Override;
use Phil\AccessBundle\Application\Contract\CurrentMember;
use Phil\AccessBundle\Domain\ValueObject\MemberId;

/**
 * The member a test signs in, or nobody.
 */
final class FixedMember implements CurrentMember
{
    public ?MemberId $member = null;

    #[Override]
    public function current(): ?MemberId
    {
        return $this->member;
    }

    public function signIn(?string $member): void
    {
        $this->member = null === $member ? null : MemberId::of($member);
    }
}
