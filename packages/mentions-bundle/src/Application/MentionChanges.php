<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Application;

use Phil\Resource\ResourceReference;

/**
 * What recording a text changed: the members it now mentions for the first time, notified, and the members it no
 * longer mentions.
 */
final readonly class MentionChanges
{
    /**
     * @param list<Member>            $added
     * @param list<ResourceReference> $removed
     */
    public function __construct(
        public array $added = [],
        public array $removed = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->added && [] === $this->removed;
    }
}
