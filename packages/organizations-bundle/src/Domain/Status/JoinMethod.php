<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Status;

/**
 * How a member joined an organization.
 */
enum JoinMethod: string
{
    /** The member created the organization, and owns it. */
    case Created = 'created';
    /** A member who manages members added them. */
    case Added = 'added';
    /** The member accepted an invitation. */
    case Invitation = 'invitation';
    /** The member's verified address is in the organization's verified domain. */
    case Domain = 'domain';
}
