<?php

declare(strict_types=1);

namespace Phil\SocialCards\Card;

use IntlBreakIterator;

/**
 * Where a line of a card may break, by the rules of the text's language: the Unicode line breaking algorithm of ICU,
 * which breaks between the ideographs of Chinese and Japanese and at the spaces of the other scripts, never before a
 * closing mark — `:`, `;`, `!`, `?` — nor inside a quotation's `«` and `»`, even when French sets them apart with a
 * space, and never at a no-break space.
 *
 * Within a piece, the no-break spaces become plain spaces, which every font draws: the image library never breaks a
 * line on its own, so they have done their work once the pieces are cut.
 */
final class LineBreaker
{
    /**
     * The pieces of the text, each ending where a line may break, its spaces kept: joined, they give the text back,
     * with plain spaces for the no-break ones.
     *
     * @return list<string>
     */
    public static function segments(string $text, string $locale): array
    {
        /** @var IntlBreakIterator $iterator ICU falls back on its root rules for a locale it does not know */
        $iterator = IntlBreakIterator::createLineInstance($locale);
        $iterator->setText($text);

        $segments = [];
        $start = 0;

        /** @var int $boundary */
        foreach ($iterator as $boundary) {
            if ($boundary > $start) {
                $segments[] = str_replace(["\u{00A0}", "\u{202F}"], ' ', substr($text, $start, $boundary - $start));
                $start = $boundary;
            }
        }

        return $segments;
    }
}
