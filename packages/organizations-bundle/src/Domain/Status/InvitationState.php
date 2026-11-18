<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Status;

/**
 * Where an invitation stands at a moment.
 */
enum InvitationState: string
{
    case Usable = 'usable';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case UsedUp = 'used_up';
}
