<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Pairs;

use Phil\RelatedContent\Reason;

/**
 * What an anonymous pair count counts.
 */
enum Signal: string
{
    /** Two products in the same order; the same pair both ways. */
    case BoughtTogether = 'bought-together';

    /** One product viewed right after another; from the first to the second only. */
    case ViewedNext = 'viewed-next';

    public function isSymmetric(): bool
    {
        return self::BoughtTogether === $this;
    }

    public function reason(): Reason
    {
        return match ($this) {
            self::BoughtTogether => Reason::BoughtTogether,
            self::ViewedNext => Reason::ViewedNext,
        };
    }
}
