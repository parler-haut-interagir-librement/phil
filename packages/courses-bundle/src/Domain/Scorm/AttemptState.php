<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Scorm;

/**
 * Where a SCORM attempt stands: a session of it running in the learner's browser; between two sessions of SCORM 1.2
 * that left it neither ended nor suspended; suspended by the learner, to resume; or ended, the next launch beginning a
 * new attempt.
 */
enum AttemptState: string
{
    case Running = 'running';
    case Paused = 'paused';
    case Suspended = 'suspended';
    case Ended = 'ended';
}
