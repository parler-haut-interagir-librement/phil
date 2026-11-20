<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application;

/**
 * The register of the application's uses of AI, read from its tasks: what a transparency page, a privacy policy or an
 * audit lists, kept true by being the configuration itself.
 */
final readonly class AiUsageRegister
{
    public function __construct(
        private TaskCatalog $tasks,
    ) {
    }

    /**
     * @return list<AiUse> in the order of the configuration
     */
    public function uses(): array
    {
        return array_map(
            static fn (TaskDefinition $task): AiUse => new AiUse($task->kind->value, $task->purpose, $task->output, $task->platform, $task->model, array_map(static fn (ToolDefinition $tool): string => $tool->name, $task->tools)),
            $this->tasks->all(),
        );
    }
}
