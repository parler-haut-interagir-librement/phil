<?php

declare(strict_types=1);

namespace Phil\Lti\Service\Nrps;

/**
 * The status of a member of a context.
 */
enum MemberStatus: string
{
    case Active = 'Active';
    case Inactive = 'Inactive';
    case Deleted = 'Deleted';
}
