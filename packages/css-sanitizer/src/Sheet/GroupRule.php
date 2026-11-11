<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Sheet;

use Phil\CssSanitizer\Syntax\ComponentValue;
use Phil\CssSanitizer\Syntax\Declaration;

/**
 * An at-rule with a block: `@media` and `@supports` hold rules — and, nested in a style rule, declarations for its
 * elements —, `@font-face` holds descriptors, `@page` holds declarations and the at-rules of its margin boxes. `name`
 * is in lower case, without `@`.
 */
final readonly class GroupRule
{
    /**
     * @param list<ComponentValue>      $prelude
     * @param list<Declaration>         $declarations
     * @param list<StyleRule|GroupRule> $rules
     */
    public function __construct(
        public string $name,
        public array $prelude = [],
        public array $declarations = [],
        public array $rules = [],
    ) {
    }
}
