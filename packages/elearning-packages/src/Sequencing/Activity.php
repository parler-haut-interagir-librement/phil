<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

use Phil\ElearningPackages\Sequencing\Prerequisite\Prerequisites;

/**
 * An activity of the tree: an organization or an item, its title, whether it launches content — a leaf names a
 * resource —, whether it is shown, its sequencing, the prerequisites of SCORM 1.2, and its children.
 */
final readonly class Activity
{
    /**
     * @param list<self> $children
     */
    public function __construct(
        public string $identifier,
        public string $title,
        public Sequencing $sequencing = new Sequencing(),
        public array $children = [],
        public bool $visible = true,
        public ?string $resource = null,
        public ?Prerequisites $prerequisites = null,
    ) {
    }

    public function isLeaf(): bool
    {
        return [] === $this->children;
    }
}
