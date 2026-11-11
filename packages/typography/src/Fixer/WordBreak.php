<?php

declare(strict_types=1);

namespace Phil\Typography\Fixer;

use IntlBreakIterator;
use Override;
use Phil\Typography\Text\Chars;
use Phil\Typography\Text\Run;

/**
 * Zero-width spaces between the words of Thai, a script written without spaces between words, so that a browser
 * without a dictionary of its own breaks a line between words rather than inside one or not at all; Thai breaks a line
 * without a visible hyphen. The breaks are the line-break opportunities of ICU, through `IntlBreakIterator` and its
 * Thai dictionary: never before `ๆ` nor inside a syllable. Only runs of Thai characters are read; a zero-width space
 * already written ends a run, so fixing twice gives the same text.
 */
final readonly class WordBreak implements Fixer
{
    /**
     * The language whose presets include the fixer.
     */
    public const string LANGUAGE = 'th';

    #[Override]
    public function fix(Run $run): Run
    {
        return $run->map(static fn (string $text): string => preg_replace_callback(
            '/\p{Thai}+/u',
            static fn (array $words): string => self::separate($words[0]),
            $text,
        ) ?? $text);
    }

    private static function separate(string $words): string
    {
        $iterator = IntlBreakIterator::createLineInstance(self::LANGUAGE);

        if (!$iterator instanceof IntlBreakIterator) {
            return $words;
        }

        $iterator->setText($words);
        $separated = '';
        $start = 0;

        for ($end = $iterator->next(); IntlBreakIterator::DONE !== $end; $end = $iterator->next()) {
            $separated .= ('' === $separated ? '' : Chars::ZERO_WIDTH_SPACE).substr($words, $start, $end - $start);
            $start = $end;
        }

        return $separated;
    }
}
