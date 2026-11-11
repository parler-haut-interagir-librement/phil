<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Syntax;

/**
 * An at-rule of CSS Syntax Level 3: its name without `@`, as written, its prelude, and its `{}` block if it has one.
 */
final readonly class AtRule
{
    /**
     * @param list<ComponentValue> $prelude
     */
    public function __construct(
        public string $name,
        public array $prelude,
        public ?SimpleBlock $block,
        public int $line = 1,
    ) {
    }

    /** The name in ASCII lower case, as CSS compares it. */
    public function lowerName(): string
    {
        return strtolower($this->name);
    }
}
