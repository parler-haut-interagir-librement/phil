<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\Exception\SequencingRefused;
use Phil\ElearningPackages\PackageFormat;

/**
 * The activity tree of one organization of a SCORM package: its root, the organization, and every item below it, each
 * found by its identifier, its parent and its path from the root; the format of the package, which decides whether the
 * prerequisites of SCORM 1.2 or the sequencing of SCORM 2004 apply; and the notes of the reading.
 */
final readonly class ActivityTree
{
    /** @var array<string, Activity> the activities by identifier, in the order of the tree */
    private array $activities;

    /** @var array<string, string> the identifier of the parent of each activity but the root */
    private array $parents;

    /**
     * @param list<string> $warnings what the reader set aside, each said once
     *
     * @throws PackageRefused when two activities share an identifier
     */
    public function __construct(
        public Activity $root,
        public PackageFormat $format = PackageFormat::Scorm2004,
        public array $warnings = [],
    ) {
        $activities = [];
        $parents = [];
        $pending = [$root];

        while ([] !== $pending) {
            $activity = array_shift($pending);

            if (isset($activities[$activity->identifier])) {
                throw PackageRefused::because(\sprintf('The identifier "%s" is used by two activities of the tree.', $activity->identifier));
            }

            $activities[$activity->identifier] = $activity;

            foreach ($activity->children as $child) {
                $parents[$child->identifier] = $activity->identifier;
            }

            array_unshift($pending, ...$activity->children);
        }

        $this->activities = $activities;
        $this->parents = $parents;
    }

    public function has(string $identifier): bool
    {
        return isset($this->activities[$identifier]);
    }

    /**
     * @throws SequencingRefused when the tree holds no activity of that identifier
     */
    public function get(string $identifier): Activity
    {
        return $this->activities[$identifier] ?? throw SequencingRefused::because(\sprintf('The activity tree holds no activity "%s".', mb_substr($identifier, 0, 120)));
    }

    public function parent(string $identifier): ?Activity
    {
        $parent = $this->parents[$identifier] ?? null;

        return null === $parent ? null : $this->activities[$parent];
    }

    /**
     * @return non-empty-list<Activity> the activities from the root down to that one, both included
     */
    public function path(string $identifier): array
    {
        $path = [$this->get($identifier)];

        while (null !== ($parent = $this->parents[$path[0]->identifier] ?? null)) {
            array_unshift($path, $this->activities[$parent]);
        }

        return $path;
    }

    /**
     * @return list<Activity> every activity, the root first, in the order of the tree
     */
    public function activities(): array
    {
        return array_values($this->activities);
    }

    /**
     * Whether the prerequisites of SCORM 1.2 apply rather than the sequencing of SCORM 2004.
     */
    public function isScorm12(): bool
    {
        return PackageFormat::Scorm12 === $this->format;
    }
}
