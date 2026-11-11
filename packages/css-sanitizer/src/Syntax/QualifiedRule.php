<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Syntax;

/**
 * A qualified rule of CSS Syntax Level 3: a prelude — the selector of a style rule — and a `{}` block.
 */
final readonly class QualifiedRule
{
    /**
     * @param list<ComponentValue> $prelude
     */
    public function __construct(
        public array $prelude,
        public SimpleBlock $block,
        public int $line = 1,
    ) {
    }
}
