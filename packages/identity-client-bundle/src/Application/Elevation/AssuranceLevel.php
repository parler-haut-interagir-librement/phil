<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Elevation;

/**
 * One rung of the assurance scale: an authentication context class, a maximum age of the authentication, or both.
 */
final readonly class AssuranceLevel
{
    public function __construct(
        public string $name,
        public ?string $acr = null,
        public ?int $maxAge = null,
    ) {
    }
}
