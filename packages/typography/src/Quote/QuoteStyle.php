<?php

declare(strict_types=1);

namespace Phil\Typography\Quote;

/**
 * The quotation marks of a language: the primary pair, and the inner pair used for a quote within a quote.
 */
final readonly class QuoteStyle
{
    public function __construct(
        public string $open,
        public string $close,
        public string $innerOpen,
        public string $innerClose,
    ) {
    }
}
