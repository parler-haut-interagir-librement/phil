<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

/**
 * Where the learner stands in an attempt, in the words of SCORM 2004 whatever the version: the completion —
 * `completed`, `incomplete`, `not attempted` or `unknown` —, the success — `passed`, `failed` or `unknown` — and the
 * score scaled from -1 to 1.
 *
 * The `lesson_status` of SCORM 1.2 reads as ADL maps it: `passed` is completed and passed, `failed` completed and
 * failed, `completed` completed, `incomplete` and `browsed` incomplete, `not attempted` not attempted.
 */
final readonly class RuntimeStatus
{
    public function __construct(
        public string $completion = 'unknown',
        public string $success = 'unknown',
        public ?float $score = null,
    ) {
    }

    public static function fromLessonStatus(string $lessonStatus, ?float $score = null): self
    {
        return match ($lessonStatus) {
            'passed' => new self('completed', 'passed', $score),
            'failed' => new self('completed', 'failed', $score),
            'completed' => new self('completed', 'unknown', $score),
            'incomplete', 'browsed' => new self('incomplete', 'unknown', $score),
            default => new self('not attempted', 'unknown', $score),
        };
    }

    public function isCompleted(): bool
    {
        return 'completed' === $this->completion;
    }

    /**
     * True when passed, false when failed, null when the content did not say.
     */
    public function success(): ?bool
    {
        return match ($this->success) {
            'passed' => true,
            'failed' => false,
            default => null,
        };
    }

    /**
     * The `lesson_status` of SCORM 1.2 this status reads as: passed or failed first, then the completion.
     */
    public function lessonStatus(): string
    {
        return match (true) {
            'passed' === $this->success => 'passed',
            'failed' === $this->success => 'failed',
            'completed' === $this->completion => 'completed',
            'incomplete' === $this->completion => 'incomplete',
            default => 'not attempted',
        };
    }
}
