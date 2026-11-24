<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application;

/**
 * A page of a subject's followers, or nothing at all when the member it belongs to hides them from the viewer: neither
 * the names nor how many.
 */
final readonly class FollowerList
{
    /**
     * @param list<string> $members
     * @param string|null  $next    the cursor of the next page, or null on the last one
     */
    public function __construct(
        public bool $hidden = false,
        public array $members = [],
        public ?int $count = null,
        public ?string $next = null,
    ) {
    }
}
