<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Application\Event;

use Phil\VideoBundle\Application\ViewingProgress;

/**
 * A viewer who agreed to be counted watched part of a video: what a course listens to, to mark a lesson seen. The event
 * says what was watched, not who watched it; a listener that needs the viewer asks the security of the application.
 */
final readonly class VideoProgressed
{
    public function __construct(
        public ViewingProgress $progress,
    ) {
    }
}
