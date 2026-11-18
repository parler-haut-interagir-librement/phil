<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application;

/**
 * Where the learner goes to take an activity: the URL, framed or in its own window, or the activity page itself.
 */
final readonly class Launch
{
    private function __construct(
        public LaunchWindow $window,
        public ?string $url = null,
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

    public static function newWindow(string $url): self
    {
        return new self(LaunchWindow::NewWindow, $url);
    }
}
