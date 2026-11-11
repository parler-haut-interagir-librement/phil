<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Domain\ValueObject;

use Phil\WellKnownBundle\Domain\Exception\InvalidValue;
use stdClass;

/**
 * The usage a server tells in NodeInfo 2.1, every count optional: a server that keeps its numbers to itself tells none.
 */
final readonly class NodeInfoUsage
{
    /**
     * @throws InvalidValue when a count is negative
     */
    public function __construct(
        public ?int $totalUsers = null,
        public ?int $activeHalfyear = null,
        public ?int $activeMonth = null,
        public ?int $localPosts = null,
        public ?int $localComments = null,
    ) {
        foreach ([$totalUsers, $activeHalfyear, $activeMonth, $localPosts, $localComments] as $count) {
            if (null !== $count && $count < 0) {
                throw InvalidValue::because('A NodeInfo count is zero or more.');
            }
        }
    }

    /**
     * The usage as its JSON members: `users` an object, empty when no user count is told.
     *
     * @return array{users: stdClass, localPosts?: int, localComments?: int}
     */
    public function toArray(): array
    {
        $usage = ['users' => (object) array_filter(['total' => $this->totalUsers, 'activeHalfyear' => $this->activeHalfyear, 'activeMonth' => $this->activeMonth], static fn (?int $count): bool => null !== $count)];

        if (null !== $this->localPosts) {
            $usage['localPosts'] = $this->localPosts;
        }

        if (null !== $this->localComments) {
            $usage['localComments'] = $this->localComments;
        }

        return $usage;
    }
}
