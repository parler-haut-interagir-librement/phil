<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application;

use Phil\CoursesBundle\Application\Scorm\ScormLaunch;

/**
 * Where the learner goes to take an activity: the URL, framed or in its own window, or the activity page itself; for
 * a SCORM activity run by the platform, the URL of its launch file framed with the session of its attempt.
 */
final readonly class Launch
{
    private function __construct(
        public LaunchWindow $window,
        public ?string $url = null,
        public ?ScormLaunch $scorm = null,
    ) {
    }

    public static function page(): self
    {
        return new self(LaunchWindow::Page);
    }

    public static function embedded(string $url): self
    {
        return new self(LaunchWindow::Embedded, $url);
    }

    /**
     * The launch file of a SCO, framed under the run-time API of its session.
     */
    public static function scorm(string $url, ScormLaunch $session): self
    {
        return new self(LaunchWindow::Embedded, $url, $session);
    }

    public static function newWindow(string $url): self
    {
        return new self(LaunchWindow::NewWindow, $url);
    }
}
