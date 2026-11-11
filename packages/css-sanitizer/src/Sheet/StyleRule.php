<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Sheet;

use Phil\CssSanitizer\Syntax\ComponentValue;
use Phil\CssSanitizer\Syntax\Declaration;

/**
 * A style rule: its selectors, each one a list of component values without the comma between them, its declarations,
 * and the rules nested in it, CSS Nesting's way — style rules whose selectors are relative to this one, and `@media`
 * and `@supports` rules that hold declarations for this rule's elements.
 */
final readonly class StyleRule
{
    /**
     * @param non-empty-list<list<ComponentValue>> $selectors
     * @param list<Declaration>                    $declarations
     * @param list<StyleRule|GroupRule>            $rules
     */
    public function __construct(
        public array $selectors,
        public array $declarations,
        public array $rules = [],
    ) {
    }
}
