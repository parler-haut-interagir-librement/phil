<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

use Phil\ElearningPackages\Runtime\Duration;
use Phil\ElearningPackages\Runtime\RuntimeStatus;
use Phil\ElearningPackages\Sequencing\Prerequisite\Prerequisites;

/**
 * What the content of the delivered activity reported, as a platform hands it to the sequencer: its completion, the
 * success of its primary objective, its scaled score, the time the attempt has lasted, the objectives it set by their
 * identifiers, the `lesson_status` of SCORM 1.2, and whether the content asked to suspend the attempt when it ends —
 * `cmi.exit` set to `suspend`. Null leaves a value as it stands.
 */
final readonly class AttemptResult
{
    /**
     * @param array<string, ObjectiveStatus> $objectives the objectives of the activity the content set, by identifier
     */
    public function __construct(
        public ?bool $completed = null,
        public ?bool $passed = null,
        public ?float $scaledScore = null,
        public ?Duration $duration = null,
        public array $objectives = [],
        public ?string $lessonStatus = null,
        public bool $suspend = false,
    ) {
    }

    /**
     * The result a run-time status reads as: `completed` or `incomplete`, `passed` or `failed`, and its score.
     */
    public static function fromRuntime(RuntimeStatus $status, ?Duration $duration = null, bool $suspend = false): self
    {
        return new self(
            match ($status->completion) {
                'completed' => true,
                'incomplete' => false,
                default => null,
            },
            $status->success(),
            $status->score,
            $duration,
            [],
            $status->lessonStatus(),
            $suspend,
        );
    }

    /**
     * The result a `lesson_status` of SCORM 1.2 reads as, kept as written — `browsed` included — for the
     * prerequisites; a value SCORM 1.2 does not define reads as `not attempted`.
     */
    public static function fromLessonStatus(string $lessonStatus, ?float $score = null, ?Duration $duration = null, bool $suspend = false): self
    {
        $result = self::fromRuntime(RuntimeStatus::fromLessonStatus($lessonStatus, $score), $duration, $suspend);

        return new self($result->completed, $result->passed, $score, $duration, [], \in_array($lessonStatus, Prerequisites::STATUSES, true) ? $lessonStatus : 'not attempted', $suspend);
    }
}
