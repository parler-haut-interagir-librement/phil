<?php

declare(strict_types=1);

namespace Phil\ContentWarnings;

/**
 * What happens to a piece of content with a warning: shown with its warning in view, folded behind its warning until
 * the reader reveals it, or hidden. A safety tool's veil folds, its line hides.
 *
 * The cases are declared from the most open to the strictest, which `isStricterThan()` reads.
 */
enum Display: string
{
    case Show = 'show';
    case Fold = 'fold';
    case Hide = 'hide';

    public function isStricterThan(self $other): bool
    {
        return array_search($this, self::cases(), true) > array_search($other, self::cases(), true);
    }

    /**
     * The strictest of the displays, `Show` for none.
     */
    public static function strictest(self ...$displays): self
    {
        $strictest = self::Show;

        foreach ($displays as $display) {
            if ($display->isStricterThan($strictest)) {
                $strictest = $display;
            }
        }

        return $strictest;
    }
}
