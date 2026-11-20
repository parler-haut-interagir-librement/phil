<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application;

use Phil\AiBundle\Application\Exception\UnknownTask;
use Phil\AiBundle\Domain\Exception\InvalidAiValue;
use Phil\AiBundle\Domain\ValueObject\TaskKind;

/**
 * The tasks the application defines, by kind.
 */
final readonly class TaskCatalog
{
    /** @var array<string, TaskDefinition> */
    private array $definitions;

    /**
     * @throws InvalidAiValue when two definitions share a kind
     */
    public function __construct(TaskDefinition ...$definitions)
    {
        $byKind = [];

        foreach ($definitions as $definition) {
            if (isset($byKind[$definition->kind->value])) {
                throw InvalidAiValue::because(\sprintf('The task "%s" is defined twice.', $definition->kind->value));
            }

            $byKind[$definition->kind->value] = $definition;
        }

        $this->definitions = $byKind;
    }

    /**
     * @throws UnknownTask
     */
    public function get(TaskKind $kind): TaskDefinition
    {
        return $this->definitions[$kind->value] ?? throw UnknownTask::of($kind->value);
    }

    /**
     * @return list<TaskDefinition> in the order of the configuration
     */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    /**
     * @return list<string>
     */
    public function kinds(): array
    {
        return array_keys($this->definitions);
    }
}
