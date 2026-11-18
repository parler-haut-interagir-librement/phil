<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Scorm;

/**
 * An item of a SCORM organization: a title, the resource it launches, with the parameters appended to its launch
 * file, and its children. What a run time hands the SCO — its launch data, the mastery score of SCORM 1.2 or the scaled
 * passing score of SCORM 2004's primary objective, the completion threshold of SCORM 2004, the time allowed and what
 * happens once it is spent — is read; the prerequisites of SCORM 1.2 and the sequencing of SCORM 2004 are kept as
 * read.
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
        public ?string $dataFromLms = null,
        public ?string $maxTimeAllowed = null,
        public ?string $timeLimitAction = null,
        public ?float $scaledPassingScore = null,
    ) {
    }

    public function isLeaf(): bool
    {
        return [] === $this->children;
    }
}
