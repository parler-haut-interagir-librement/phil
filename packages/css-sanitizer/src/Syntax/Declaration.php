<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Syntax;

/**
 * A declaration of CSS Syntax Level 3: a property name as written, its value without the white space around it, and
 * whether it is `!important`.
 */
final readonly class Declaration
{
    /**
     * @param list<ComponentValue> $value
     */
    public function __construct(
        public string $name,
        public array $value,
        public bool $important = false,
        public int $line = 1,
    ) {
    }

    /** Whether the name is a custom property's, starting with two hyphens. */
    public function isCustomProperty(): bool
    {
        return str_starts_with($this->name, '--');
    }

    /** The name as CSS compares it: a custom property's as written, any other in ASCII lower case. */
    public function property(): string
    {
        return $this->isCustomProperty() ? $this->name : strtolower($this->name);
    }
}
