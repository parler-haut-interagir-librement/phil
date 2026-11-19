<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Application;

use Phil\ErrorTracking\Frame;

/**
 * An error a browser reported, read and bounded: its type, its message, the page it happened on and the frames of its
 * stack, the innermost first.
 */
final readonly class BrowserError
{
    /**
     * @param list<Frame> $frames
     */
    public function __construct(
        public string $type,
        public string $message,
        public string $url,
        public array $frames,
    ) {
    }
}
