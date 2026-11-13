<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tree;

use Phil\Resource\ResourceReference;

/**
 * The decision, the node whose entries gave it, and the grantees of those entries — the member's roles, or everyone
 * else —, to show why.
 */
final readonly class TreeDecision
{
    /**
     * @param list<Grantee> $grantees
     */
    public function __construct(
        public Decision $decision,
        public ?ResourceReference $node = null,
        public array $grantees = [],
    ) {
    }

    public function isAllowed(): bool
    {
        return Decision::Allow === $this->decision;
    }
}
