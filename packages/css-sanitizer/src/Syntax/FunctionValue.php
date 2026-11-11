<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Syntax;

use Override;

/**
 * A function of CSS Syntax Level 3: its name as written and the component values of its arguments.
 */
final readonly class FunctionValue implements ComponentValue
{
    /**
     * @param list<ComponentValue> $arguments
     */
    public function __construct(
        public string $name,
        public array $arguments,
        public int $sourceLine = 1,
    ) {
    }

    #[Override]
    public function line(): int
    {
        return $this->sourceLine;
    }

    /** The name in ASCII lower case, as CSS compares it. */
    public function lowerName(): string
    {
        return strtolower($this->name);
    }
}
