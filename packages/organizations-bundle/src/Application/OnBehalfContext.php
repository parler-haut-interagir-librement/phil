<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

/**
 * The organization a member is acting on behalf of, during `OnBehalf::act()`: the organization is the author shown,
 * the member the real one.
 */
final readonly class OnBehalfContext
{
    public function __construct(
        public string $organizationId,
        public string $authorId,
        public string $action,
    ) {
    }
}
