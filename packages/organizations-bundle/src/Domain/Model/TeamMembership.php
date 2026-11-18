<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Model;

use DateTimeImmutable;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\Moment;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Phil\OrganizationsBundle\Domain\ValueObject\TeamId;

/**
 * One member of one team, with one or more roles of the team.
 */
final class TeamMembership
{
    private function __construct(
        public readonly TeamId $teamId,
        public readonly MemberId $memberId,
        public private(set) Roles $roles,
        public readonly DateTimeImmutable $joinedAt,
    ) {
    }

    public static function join(TeamId $teamId, MemberId $memberId, Roles $roles, DateTimeImmutable $now): self
    {
        return new self($teamId, $memberId, $roles, Moment::of($now));
    }

    /**
     * A team membership read back from a store.
     */
    public static function restored(TeamId $teamId, MemberId $memberId, Roles $roles, DateTimeImmutable $joinedAt): self
    {
        return new self($teamId, $memberId, $roles, $joinedAt);
    }

    public function changeRoles(Roles $roles): void
    {
        $this->roles = $roles;
    }
}
