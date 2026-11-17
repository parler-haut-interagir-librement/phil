<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Domain\Status;

/**
 * Why a view of the site as a member ended.
 */
enum ViewAsEnd: string
{
    /** The admin stopped it. */
    case Stopped = 'stopped';

    /** The admin is no longer an admin of the space, or no longer signed in. */
    case AdminRightLost = 'admin_right_lost';

    /** The member viewed is no longer a member of the space. */
    case MemberLeftSpace = 'member_left_space';
}
