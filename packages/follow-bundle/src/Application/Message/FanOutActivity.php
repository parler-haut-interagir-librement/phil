<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application\Message;

use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Identifier;

/**
 * Sends an activity to its followers, one page at a time: the identifier of the activity, which the worker reads
 * again, and the last member of the page before. Nothing else travels.
 */
final readonly class FanOutActivity
{
    /**
     * @throws InvalidFollow when an identifier is of the wrong shape
     */
    public function __construct(
        public string $activity,
        public ?string $after = null,
    ) {
        Identifier::id($activity, 'activity');

        if (null !== $after) {
            Identifier::id($after, 'member');
        }
    }
}
