<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

use Phil\OrganizationsBundle\Domain\Status\LeaveReason;

/**
 * A member is no longer a member: left, removed or erased, with the roles they held; the actor is the member who
 * removed them, when one did.
 */
final readonly class MemberLeft
{
    public function __construct(
        public string $organizationId,
        public string $memberId,
        /** @var list<string> */
        public array $roles,
        public LeaveReason $reason,
        public ?string $actorId = null,
    ) {
    }
}
