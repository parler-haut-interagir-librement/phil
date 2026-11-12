<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Application;

use Phil\Almanac\Definition;
use Phil\Almanac\Exception\InvalidRule;
use Phil\AlmanacBundle\Domain\Model\StoredDate;
use Phil\AlmanacBundle\Domain\Repository\StoredDateRepository;

/**
 * The almanac's dates: those of the configuration, then those of the database, which replace a configured date of the
 * same key — an administrator changes a date without a deployment. A stored date whose rule no longer reads — a column
 * changed by hand — is left out, for one row not to stop every page.
 */
final readonly class Definitions
{
    /**
     * @param list<Definition> $configured
     */
    public function __construct(
        private array $configured,
        private ?StoredDateRepository $stored = null,
    ) {
    }

    /**
     * @return list<Definition>
     */
    public function all(): array
    {
        $byKey = [];

        foreach ($this->configured as $definition) {
            $byKey[$definition->key] = $definition;
        }

        foreach ($this->stored?->all() ?? [] as $date) {
            try {
                $byKey[$date->key()] = $date->definition();
            } catch (InvalidRule) {
                continue;
            }
        }

        return array_values($byKey);
    }

    /**
     * Whether a key is the configuration's, which a stored date of the same key replaces.
     */
    public function isConfigured(string $key): bool
    {
        return [] !== array_filter($this->configured, static fn (Definition $definition): bool => $definition->key === $key);
    }

    /**
     * @return list<StoredDate>
     */
    public function stored(): array
    {
        return $this->stored?->all() ?? [];
    }
}
