<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application\Message;

/**
 * Deletes the activities past their retention, the claims of their deliveries, and the marks of notifications too old
 * to hold the next one back.
 */
final readonly class PurgeFollowRecords
{
}
