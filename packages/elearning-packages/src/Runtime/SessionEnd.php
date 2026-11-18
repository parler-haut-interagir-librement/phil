<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

/**
 * What the end of a session leaves: where the learner stands, the total time of the attempt, whether the learner
 * suspended it to resume later, whether the attempt is over — the next launch begins a new one — and the navigation
 * request of SCORM 2004 the content made, if any.
 */
final readonly class SessionEnd
{
    public function __construct(
        public RuntimeStatus $status,
        public Duration $totalTime,
        public bool $suspended,
        public bool $attemptEnded,
        public ?string $navigation = null,
    ) {
    }
}
