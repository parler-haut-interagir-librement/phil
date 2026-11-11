<?php

declare(strict_types=1);

namespace Phil\CssSanitizer;

use Phil\CssSanitizer\Sheet\StyleSheet;
use Phil\CssSanitizer\Syntax\Declaration;

/**
 * What the sanitizer kept, as CSS and as values, and what it removed.
 *
 * A style sheet fills `sheet`; the declarations of a `style` attribute fill `declarations`. `css` is what to write in
 * the page. The removals are listed up to `MAX_REMOVALS`; `omittedRemovals` counts those past them.
 */
final readonly class Sanitized
{
    /** The most removals listed: a source can hold hundreds of thousands, and the others are only counted. */
    public const int MAX_REMOVALS = 100;

    /**
     * @param list<Removal>     $removals        the first `MAX_REMOVALS` removals, in the order of the source
     * @param list<Declaration> $declarations
     * @param int               $omittedRemovals the removals past the first `MAX_REMOVALS`, counted but not listed
     */
    public function __construct(
        public string $css,
        public array $removals = [],
        public StyleSheet $sheet = new StyleSheet(),
        public array $declarations = [],
        public int $omittedRemovals = 0,
    ) {
    }

    /** Whether nothing was removed: the source was allowed as it was. */
    public function isClean(): bool
    {
        return [] === $this->removals;
    }
}
