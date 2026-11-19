<?php

declare(strict_types=1);

namespace Phil\AiBundle\Domain\ValueObject;

use Phil\AiBundle\Domain\Exception\InvalidAiValue;

/**
 * What a task does, as the application names it — `draft.rewrite`, `character.describe` — and the stem of its prompt
 * templates: up to 64 lowercase letters, digits, "_", "." or "-", starting with a letter.
 */
final readonly class TaskKind
{
    public const int MAX_LENGTH = 64;

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidAiValue
     */
    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $value)) {
            throw InvalidAiValue::because(\sprintf('"%s" is not a task kind: up to 64 lowercase letters, digits, "_", "." or "-", starting with a letter.', $value));
        }

        return new self($value);
    }
}
