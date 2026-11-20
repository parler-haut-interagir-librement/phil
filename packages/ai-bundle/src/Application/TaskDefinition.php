<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application;

use Phil\AiBundle\Domain\Exception\InvalidAiValue;
use Phil\AiBundle\Domain\Journal\Output;
use Phil\AiBundle\Domain\ValueObject\TaskKind;

/**
 * How a kind of task runs: on which platform, with which model and options, how many tokens must be left in the
 * subject's quota before it starts, what it asks for — text, structured data, an image, speech —, the tools the model
 * may propose to call, and the purpose the usage register states.
 */
final readonly class TaskDefinition
{
    public const string PLATFORM_PATTERN = '/^[a-z][a-z0-9_-]{0,63}$/D';

    /** @var non-empty-string */
    public string $model;

    /**
     * @param array<string, mixed> $options   passed as they are to the platform: `max_tokens`, `temperature`…
     * @param class-string|null    $structure the class a structured output is read into
     * @param list<ToolDefinition> $tools
     * @param string               $purpose   what the task is for, in a sentence, for the usage register
     *
     * @throws InvalidAiValue
     */
    public function __construct(
        public TaskKind $kind,
        public string $platform,
        string $model,
        public array $options = [],
        public int $reserve = 1,
        public Output $output = Output::Text,
        public ?string $structure = null,
        public array $tools = [],
        public string $purpose = '',
    ) {
        if (1 !== preg_match(self::PLATFORM_PATTERN, $platform)) {
            throw InvalidAiValue::because(\sprintf('"%s" is not a platform name: up to 64 lowercase letters, digits, "_" or "-", starting with a letter.', $platform));
        }

        if ('' === $model || \strlen($model) > 128) {
            throw InvalidAiValue::because('A model is named by 1 to 128 bytes.');
        }

        $this->model = $model;

        if ($reserve < 1) {
            throw InvalidAiValue::because(\sprintf('A task reserves one token or more; got %d.', $reserve));
        }

        if ((Output::Structured === $output) !== (null !== $structure)) {
            throw InvalidAiValue::because(\sprintf('The task "%s" names the class of its structured output when, and only when, it asks for one.', $kind->value));
        }

        $names = [];

        foreach ($tools as $tool) {
            if (Output::Image === $output || Output::Speech === $output) {
                throw InvalidAiValue::because(\sprintf('The task "%s" generates media and proposes no tool call.', $kind->value));
            }

            if (\in_array($tool->name, $names, true)) {
                throw InvalidAiValue::because(\sprintf('The task "%s" names the tool "%s" twice.', $kind->value, $tool->name));
            }

            $names[] = $tool->name;
        }
    }
}
