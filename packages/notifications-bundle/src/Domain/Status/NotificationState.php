<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Domain\Status;

/**
 * Where a notification stands in its recipient's centre.
 */
enum NotificationState: string
{
    /** Not seen yet: counted on the bell. */
    case Unread = 'unread';

    /** Seen, still listed. */
    case Read = 'read';

    /** Put away: no longer listed, kept until the retention purge. */
    case Archived = 'archived';
}
