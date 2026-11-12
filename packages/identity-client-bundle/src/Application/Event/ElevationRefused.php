<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Event;

/**
 * The provider answered without reaching the assurance level that was asked for.
 */
final readonly class ElevationRefused
{
    public function __construct(
        public ?string $subject,
        public string $level,
    ) {
    }
}
