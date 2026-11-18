<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

use Phil\OrganizationsBundle\Domain\Status\JoinMethod;

/**
 * A member joined an organization with roles: created it, added, by an invitation or by the verified domain; the actor
 * is the member who added them, when one did.
 */
final readonly class MemberJoined
{
    public function __construct(
        public string $organizationId,
        public string $memberId,
        /** @var list<string> */
        public array $roles,
        public JoinMethod $method,
        public ?string $actorId = null,
    ) {
    }
}
