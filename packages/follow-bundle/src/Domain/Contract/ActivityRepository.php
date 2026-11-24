<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\FollowBundle\Domain\Model\Activity;

/**
 * The activities waiting to be sent, or sent and kept until their retention ends.
 */
interface ActivityRepository
{
    /**
     * Keeps the activity, once: an activity whose identifier is known is left as it is.
     *
     * @return bool whether it was new
     */
    public function record(Activity $activity): bool;

    public function find(string $id): ?Activity;

    /**
     * Deletes up to `$limit` activities that occurred before the instant.
     *
     * @return int how many
     */
    public function purgeBefore(DateTimeImmutable $before, int $limit): int;

    /**
     * Deletes the activities the member did and takes them out of the mentions of the others.
     */
    public function eraseMember(string $member): void;
}
