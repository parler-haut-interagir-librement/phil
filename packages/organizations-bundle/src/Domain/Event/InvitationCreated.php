<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * An invitation created; its token went to its creator only.
 */
final readonly class InvitationCreated
{
    public function __construct(
        public string $organizationId,
        public string $invitationId,
        public string $actorId,
    ) {
    }
}
