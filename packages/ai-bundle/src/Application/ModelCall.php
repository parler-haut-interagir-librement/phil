<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application;

use Phil\AiBundle\Domain\Journal\Output;

/**
 * One call to a model: the platform, the model, the prompt and the options of the task, what it asks for, and the
 * tools the model may propose to call.
 */
final readonly class ModelCall
{
    /**
     * @param non-empty-string     $model
     * @param array<string, mixed> $options
     * @param class-string|null    $structure the class of a structured output
     * @param list<ToolDefinition> $tools
     */
    public function __construct(
        public string $platform,
        public string $model,
        public Prompt $prompt,
        public array $options = [],
        public Output $output = Output::Text,
        public ?string $structure = null,
        public array $tools = [],
    ) {
    }

    public static function of(TaskDefinition $definition, Prompt $prompt): self
    {
        return new self($definition->platform, $definition->model, $prompt, $definition->options, $definition->output, $definition->structure, $definition->tools);
    }
}
