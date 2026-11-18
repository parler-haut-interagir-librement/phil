<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Status;

/**
 * Why a member is no longer a member of an organization.
 */
enum LeaveReason: string
{
    /** The member left. */
    case Left = 'left';
    /** A member who manages members removed them. */
    case Removed = 'removed';
    /** The member's personal data was erased. */
    case Erased = 'erased';
}
