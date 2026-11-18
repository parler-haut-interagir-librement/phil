<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Model;

use DateTimeImmutable;
use Phil\OrganizationsBundle\Domain\Status\JoinMethod;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\Moment;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;

/**
 * One member of one organization, with one or more roles.
 */
final class Membership
{
    private function __construct(
        public readonly OrganizationId $organizationId,
        public readonly MemberId $memberId,
        public private(set) Roles $roles,
        public readonly JoinMethod $method,
        public readonly DateTimeImmutable $joinedAt,
    ) {
    }

    public static function join(OrganizationId $organizationId, MemberId $memberId, Roles $roles, JoinMethod $method, DateTimeImmutable $now): self
    {
        return new self($organizationId, $memberId, $roles, $method, Moment::of($now));
    }

    /**
     * A membership read back from a store.
     */
    public static function restored(OrganizationId $organizationId, MemberId $memberId, Roles $roles, JoinMethod $method, DateTimeImmutable $joinedAt): self
    {
        return new self($organizationId, $memberId, $roles, $method, $joinedAt);
    }

    public function changeRoles(Roles $roles): void
    {
        $this->roles = $roles;
    }
}
