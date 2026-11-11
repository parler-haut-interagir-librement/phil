<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Sheet;

/**
 * A sanitized style sheet: its `@import` rules, which come first, and its other rules, each one allowed by the policy
 * that produced it.
 */
final readonly class StyleSheet
{
    /**
     * @param list<StyleRule|GroupRule> $rules
     * @param list<ImportRule>          $imports
     */
    public function __construct(
        public array $rules = [],
        public array $imports = [],
    ) {
    }
}
