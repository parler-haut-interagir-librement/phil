<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Sheet;

use Phil\CssSanitizer\Syntax\ComponentValue;

/**
 * An `@import` rule: the URL of the style sheet, as the rewriter wrote it, and its conditions — a `supports()`
 * condition and media queries —, as written.
 */
final readonly class ImportRule
{
    /**
     * @param list<ComponentValue> $conditions
     */
    public function __construct(
        public string $url,
        public array $conditions = [],
    ) {
    }
}
