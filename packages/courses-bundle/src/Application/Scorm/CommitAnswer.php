<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Scorm;

use Phil\CoursesBundle\Domain\ValueObject\ActivityId;

/**
 * What a commit answers the page of the session: the elements left out, with their error codes; the token of the next
 * commit, none once the session ended; after a navigation request of SCORM 2004, the activity to go to, or whether to
 * leave for the outline; and under a time limit, the seconds left before the deadline, and whether the time is up.
 */
final readonly class CommitAnswer
{
    /**
     * @param array<string, int> $errors
     */
    public function __construct(
        public array $errors = [],
        public ?string $token = null,
        public bool $ended = false,
        public ?ActivityId $next = null,
        public bool $exit = false,
        public ?int $remaining = null,
        public bool $timeUp = false,
    ) {
    }
}
