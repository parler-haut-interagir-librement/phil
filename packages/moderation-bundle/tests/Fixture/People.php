<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Fixture;

use Override;
use Phil\IncidentMode\Contract\EstablishedMembers;
use Phil\ModerationBundle\Application\Contract\CurrentMember;
use Phil\ModerationBundle\Application\Contract\CurrentModerator;
use Phil\ModerationBundle\Application\Contract\TrustedHiders;
use Phil\ModerationBundle\Application\Moderator;
use Phil\ModerationBundle\Application\Role;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;

/**
 * Who is signed in, whether they moderate and under which role, who is trusted to hide and who is established: set by
 * each test.
 */
final class People implements CurrentMember, CurrentModerator, TrustedHiders, EstablishedMembers
{
    public ?MemberId $member = null;

    public ?Role $role = null;

    /** @var list<string> */
    public array $trusted = [];

    /** @var list<string> */
    public array $established = [];

    public function signIn(string $member, ?Role $role = null): self
    {
        $this->member = MemberId::of($member);
        $this->role = $role;

        return $this;
    }

    public function signOut(): self
    {
        $this->member = null;
        $this->role = null;

        return $this;
    }

    #[Override]
    public function member(): ?MemberId
    {
        return $this->member;
    }

    #[Override]
    public function moderator(): ?Moderator
    {
        return $this->member instanceof MemberId && $this->role instanceof Role ? new Moderator($this->member, $this->role) : null;
    }

    #[Override]
    public function canHide(MemberId $member): bool
    {
        return \in_array($member->value, $this->trusted, true);
    }

    #[Override]
    public function isEstablished(string $member): bool
    {
        return \in_array($member, $this->established, true);
    }
}
