<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Application;

/**
 * A scope the configuration declares: its name, what it allows, said for the developers who read the list, and the
 * translation key of its label for the members who choose it.
 */
final readonly class ScopeDefinition
{
    public function __construct(
        public string $name,
        public string $description,
        public string $label,
    ) {
    }
}
