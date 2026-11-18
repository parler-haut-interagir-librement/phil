<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

use DateTimeImmutable;

/**
 * A member acted on behalf of an organization: the organization is shown as the author, and this event records the real
 * one beside it, for an audit trail or any listener.
 */
final readonly class ActedOnBehalf
{
    public function __construct(
        public string $organizationId,
        public string $authorId,
        public string $action,
        public DateTimeImmutable $actedAt,
    ) {
    }
}
