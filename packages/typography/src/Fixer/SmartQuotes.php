<?php

declare(strict_types=1);

namespace Phil\Typography\Fixer;

use Override;
use Phil\Typography\Quote\QuoteStyle;
use Phil\Typography\Text\Characters;
use Phil\Typography\Text\Run;

/**
 * Straight quotes become the quotation marks of the language. A double quote opens after a space, the start of the
 * block or an opening mark, when a character follows it; it closes otherwise. A single quote is an inner quotation
 * mark only when it opens in that position and a matching single quote closes before a space or a punctuation mark;
 * every other single quote is an apostrophe, left to `CurlyQuote`. The block is read as a whole, across its inline
 * elements, so `"<em>word</em>"` is quoted as one.
 */
final readonly class SmartQuotes implements Fixer
{
    public function __construct(
        private QuoteStyle $style = new QuoteStyle('“', '”', '‘', '’'),
    ) {
    }

    #[Override]
    public function fix(Run $run): Run
    {
        return $run->characters(function (array $characters): array {
            $rewritten = $characters;
            $singles = [];

            foreach ($characters as $index => $character) {
                $before = $rewritten[$index - 1] ?? '';
                $after = $characters[$index + 1] ?? '';
                $opens = Characters::opensAfter($before) && '' !== $after && !Characters::isSpace($after);

                if ('"' === $character) {
                    $rewritten[$index] = $opens ? $this->style->open : $this->style->close;
                } elseif ("'" === $character && $opens) {
                    $singles[] = $index;
                } elseif ("'" === $character && [] !== $singles && !Characters::opensAfter($before) && Characters::closesBefore($after)) {
                    $rewritten[array_pop($singles)] = $this->style->innerOpen;
                    $rewritten[$index] = $this->style->innerClose;
                }
            }

            return $rewritten;
        });
    }
}
