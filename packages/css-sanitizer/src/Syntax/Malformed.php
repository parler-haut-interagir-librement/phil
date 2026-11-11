<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Syntax;

/**
 * What the parser skipped as a parse error: a declaration without its colon, text that starts neither a declaration
 * nor an at-rule in a declaration list, or a qualified rule cut by the end of the source before its block. CSS
 * Syntax Level 3 drops it; the parser keeps a trace of it so that the sanitizer can report it.
 */
final readonly class Malformed
{
    /**
     * @param list<ComponentValue> $content
     */
    public function __construct(
        public array $content,
        public int $line = 1,
    ) {
    }
}
