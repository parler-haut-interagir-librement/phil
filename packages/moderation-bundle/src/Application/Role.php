<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use Phil\ModerationBundle\Domain\Identifiers;

/**
 * A role of the moderation: its name, which the public journal shows, the security attribute that grants it, and the
 * target types it decides on — every type when none is listed.
 */
final readonly class Role
{
    /**
     * @param list<string> $types
     */
    public function __construct(
        public string $name,
        public string $attribute,
        public array $types = [],
    ) {
        Identifiers::code($name, 'a role');
    }

    public function covers(string $type): bool
    {
        return [] === $this->types || \in_array($type, $this->types, true);
    }
}
