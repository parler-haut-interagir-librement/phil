<?php

declare(strict_types=1);

namespace Phil\Automations\Definition;

use Override;
use Phil\Automations\Contract\Definitions;

/**
 * The definitions in memory, for tests and for definitions written in code.
 */
final class InMemoryDefinitions implements Definitions
{
    /** @var array<string, array<int, AutomationDefinition>> */
    private array $versions = [];

    /** @var array<string, int> the published version of each enabled automation */
    private array $enabled = [];

    /**
     * @param iterable<AutomationDefinition> $definitions published and enabled, in order
     */
    public function __construct(iterable $definitions = [])
    {
        foreach ($definitions as $definition) {
            $this->publish($definition);
        }
    }

    #[Override]
    public function enabledFor(string $triggerType): iterable
    {
        foreach ($this->enabled as $id => $version) {
            $definition = $this->versions[$id][$version];

            if ($definition->trigger->type === $triggerType) {
                yield $definition;
            }
        }
    }

    #[Override]
    public function get(AutomationId $id, int $version): ?AutomationDefinition
    {
        return $this->versions[$id->value][$version] ?? null;
    }

    #[Override]
    public function publish(AutomationDefinition $definition, bool $enabled = true): void
    {
        $this->versions[$definition->id->value][$definition->version] = $definition;
        unset($this->enabled[$definition->id->value]);

        if ($enabled) {
            $this->enabled[$definition->id->value] = $definition->version;
        }
    }

    #[Override]
    public function disable(AutomationId $id): void
    {
        unset($this->enabled[$id->value]);
    }
}
