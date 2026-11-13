<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application\Event;

/**
 * A member was taken out of an access group; identifiers only. A membership that ends on its own raises no event.
 */
final readonly class MemberLeftGroup
{
    public function __construct(
        public string $group,
        public string $member,
    ) {
    }
}
