<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Event;

/**
 * The application sent the browser back to the provider to reach an assurance level.
 */
final readonly class ElevationRequested
{
    public function __construct(
        public string $level,
    ) {
    }
}
