<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * An invitation revoked: by a member who manages members, or because the personal data of its creator was erased.
 */
final readonly class InvitationRevoked
{
    public function __construct(
        public string $organizationId,
        public string $invitationId,
        public ?string $actorId = null,
    ) {
    }
}
