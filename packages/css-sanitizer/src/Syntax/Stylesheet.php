<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Syntax;

/**
 * A style sheet as CSS Syntax Level 3 parses it: a list of rules, before any grammar of its at-rules is applied.
 */
final readonly class Stylesheet
{
    /**
     * @param list<QualifiedRule|AtRule|Malformed> $rules
     */
    public function __construct(
        public array $rules,
    ) {
    }
}
