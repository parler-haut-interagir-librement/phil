<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * An organization left without any owner, because the personal data of its last owner was erased: an operator names a
 * new owner.
 */
final readonly class OrganizationLeftWithoutOwner
{
    public function __construct(
        public string $organizationId,
    ) {
    }
}
