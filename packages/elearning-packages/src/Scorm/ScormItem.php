<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Scorm;

/**
 * An item of a SCORM organization: a title, the resource it launches, with the parameters appended to its launch
 * file, and its children. What a runtime would enforce — the mastery score and the prerequisites of SCORM 1.2, the
 * completion threshold and the sequencing of SCORM 2004 — is read and kept for information.
 */
final readonly class ScormItem
{
    /**
     * @param list<self> $children
     */
    public function __construct(
        public string $identifier,
        public string $title,
        public ?string $resource = null,
        public ?string $parameters = null,
        public bool $visible = true,
        public array $children = [],
        public ?float $masteryScore = null,
        public ?string $prerequisites = null,
        public ?float $completionThreshold = null,
        public ?string $sequencing = null,
    ) {
    }

    public function isLeaf(): bool
    {
        return [] === $this->children;
    }
}
