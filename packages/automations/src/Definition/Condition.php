<?php

declare(strict_types=1);

namespace Phil\Automations\Definition;

use Phil\Automations\Exception\InvalidDefinition;

/**
 * A condition of a filter or a branch: its source, in a language a condition evaluator reads — `expression` for
 * Symfony's ExpressionLanguage, or the name of another evaluator the application registers.
 */
final readonly class Condition
{
    public const int MAX_SOURCE_BYTES = 2048;

    private function __construct(
        public string $language,
        public string $source,
    ) {
    }

    /**
     * @throws InvalidDefinition when the language is not a name, or the source is empty or too long
     */
    public static function of(string $language, string $source): self
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,31}$/D', $language)) {
            throw InvalidDefinition::because(\sprintf('"%s" is not a condition language: a lowercase letter, then up to 31 lowercase letters, digits, "_", "." or "-".', $language));
        }

        if ('' === trim($source) || \strlen($source) > self::MAX_SOURCE_BYTES || 1 !== preg_match('//u', $source)) {
            throw InvalidDefinition::because(\sprintf('A condition is valid UTF-8, not blank, of at most %d bytes.', self::MAX_SOURCE_BYTES));
        }

        return new self($language, $source);
    }

    public static function expression(string $source): self
    {
        return self::of('expression', $source);
    }
}
