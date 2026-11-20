<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

use Phil\ElearningPackages\Runtime\Duration;

/**
 * What a session knows of one activity: its attempts, whether its attempt is under way or suspended, whether it is
 * completed — null while unknown —, the duration of its attempt, its objectives as the activity holds them, before
 * the global objectives it reads, and the `lesson_status` of SCORM 1.2 last reported.
 */
final readonly class ActivityStatus
{
    /**
     * @param array<string, ObjectiveStatus> $objectives by the key of each objective, `#primary` for a primary objective
     *                                                   without identifier
     */
    public function __construct(
        public int $attempts = 0,
        public bool $active = false,
        public bool $suspended = false,
        public ?bool $completed = null,
        public ?Duration $duration = null,
        public array $objectives = [],
        public ?string $lessonStatus = null,
    ) {
    }

    public function objective(string $key): ObjectiveStatus
    {
        return $this->objectives[$key] ?? new ObjectiveStatus();
    }
}
