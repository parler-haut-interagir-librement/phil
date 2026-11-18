<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * An invitation used by a member, who joined with its roles.
 */
final readonly class InvitationAccepted
{
    public function __construct(
        public string $organizationId,
        public string $invitationId,
        public string $memberId,
    ) {
    }
}
