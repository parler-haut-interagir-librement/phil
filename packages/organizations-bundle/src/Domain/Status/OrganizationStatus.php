<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Status;

/**
 * Whether an organization is in use, or archived: read only, its members kept, until it is restored.
 */
enum OrganizationStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
