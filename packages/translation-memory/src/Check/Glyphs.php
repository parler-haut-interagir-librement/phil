<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Check;

use Override;
use Phil\Fonts\Font;
use Phil\TranslationMemory\Check\Contract\Check;
use Phil\TranslationMemory\Text;

/**
 * The characters a template's font can draw: every character of a target, white space aside, is in the character map
 * of the face `phil/fonts` read — else the reader sees a fallback font, or an empty box, and the measured size is
 * wrong.
 */
final readonly class Glyphs implements Check
{
    public const string NAME = 'glyphs';

    /** The most missing characters an issue lists. */
    private const int SHOWN = 10;

    public function __construct(
        private Font $font,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(Checked $checked): array
    {
        $missing = [];

        foreach (mb_str_split(preg_replace('/\s+/u', '', Text::plain($checked->target)) ?? '') as $character) {
            if (!isset($missing[$character]) && !$this->covers(mb_ord($character))) {
                $missing[$character] = \sprintf('“%s” (U+%04X)', $character, mb_ord($character));
            }
        }

        if ([] === $missing) {
            return [];
        }

        return [new CheckIssue(self::NAME, \sprintf('The font %s cannot draw %s.', '' === $this->font->family() ? 'of the template' : '“'.$this->font->family().'”', implode(', ', \array_slice($missing, 0, self::SHOWN))))];
    }

    /**
     * Whether the character map covers a code point: a binary search of its sorted runs.
     */
    private function covers(int $codePoint): bool
    {
        $ranges = $this->font->unicodeRanges;
        $low = 0;
        $high = \count($ranges) - 1;

        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);
            [$first, $last] = $ranges[$middle];

            if ($codePoint < $first) {
                $high = $middle - 1;
            } elseif ($codePoint > $last) {
                $low = $middle + 1;
            } else {
                return true;
            }
        }

        return false;
    }
}
