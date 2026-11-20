<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application;

use Phil\AiBundle\Domain\Exception\InvalidAiValue;

/**
 * A tool the model may propose to call: its name, what it does, and the JSON schema of its arguments. The bundle never
 * runs it: a call the model proposes comes back in the suggestion, for the application to run once a person agrees.
 */
final readonly class ToolDefinition
{
    public const string NAME_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/D';

    /**
     * @param array<string, mixed>|null $parameters the JSON schema of the arguments, an object schema; null for none
     *
     * @throws InvalidAiValue
     */
    public function __construct(
        public string $name,
        public string $description,
        public ?array $parameters = null,
    ) {
        if (1 !== preg_match(self::NAME_PATTERN, $name)) {
            throw InvalidAiValue::because(\sprintf('"%s" is not a tool name: 1 to 64 letters, digits, "_" or "-".', $name));
        }

        if ('' === trim($description) || \strlen($description) > 1024) {
            throw InvalidAiValue::because(\sprintf('The tool "%s" is described in 1 to 1024 bytes.', $name));
        }
    }
}
