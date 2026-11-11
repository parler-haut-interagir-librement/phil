<?php

declare(strict_types=1);

namespace Phil\TextDiff;

use Phil\TextDiff\Exception\InvalidText;

/**
 * How a text is cut into the tokens two texts are compared by. The tokens of a text, put end to end, give the text
 * back byte for byte: nothing is dropped, not even white space.
 *
 * - `Words`: runs of letters, marks, digits and `_`; runs of white space; every other character alone. A character of
 *   a script written without spaces between words (Han, Hiragana, Katakana, Thai, Lao, Khmer, Myanmar) is a token
 *   alone, with the marks that belong to it: such a text is compared character by character.
 * - `Lines`: each line without its line break, and each line break.
 * - `Paragraphs`: each paragraph, and each run of blank lines between two paragraphs.
 * - `Html`: as `Words`, but a tag, a comment or a character reference is one token, never cut. A tag is read by the
 *   grammar of its attributes, so that a `>` in a quoted value stays inside it; a `<` that does not open a whole tag
 *   is text.
 */
enum Tokenizer: string
{
    case Words = 'words';
    case Lines = 'lines';
    case Paragraphs = 'paragraphs';
    case Html = 'html';

    private const string WORD = '[\p{L}\p{M}\p{N}_]++|\s++|\X';

    /**
     * As `WORD`, but a run of letters stops at a character of a script written without spaces, or at the prolonged
     * sound mark Japanese shares, which `\X` then takes alone with its marks. Letters, marks and digits are every
     * character that is not punctuation, a symbol, a separator or a control.
     */
    private const string UNSPACED_WORD = '(?:[^\p{P}\p{S}\p{Z}\p{C}\p{Han}\p{Hiragana}\p{Katakana}\p{Thai}\p{Lao}\p{Khmer}\p{Myanmar}\x{30FC}]|_)++|\s++|\X';

    /** A start or end tag, or a declaration: its name, then attributes, each unquoted or in single or double quotes. */
    private const string TAG = '<[A-Za-z!\/][^\s>]*+(?:\s++[^\s"\'>\/=]++(?:\s*+=\s*+(?:"[^"]*+"|\'[^\']*+\'|[^\s"\'>]++))?)*+\s*+\/?>';

    /**
     * @return list<string>
     *
     * @throws InvalidText when the text is not valid UTF-8
     */
    public function tokens(string $text): array
    {
        // Those scripts are all past U+0DFF, written in three or four bytes: a text without such a byte keeps the faster
        // pattern, which cuts it the same.
        $word = 1 === preg_match('/[\xE0-\xFF]/', $text) ? self::UNSPACED_WORD : self::WORD;
        $pattern = match ($this) {
            self::Words => '/'.$word.'/u',
            self::Lines => '/\r\n|[\r\n]|[^\r\n]+/u',
            self::Paragraphs => '/\R(?:\h*\R)+|(?:(?!\R\h*\R).)+/su',
            self::Html => '/<!--.*?-->|'.self::TAG.'|&#?[A-Za-z0-9]+;|'.$word.'/su',
        };

        if (false === preg_match_all($pattern, $text, $matches)) {
            throw InvalidText::unreadable();
        }

        return $matches[0];
    }

    /**
     * Whether the tokens are markup whose tags the HTML of a difference must keep.
     */
    public function isMarkup(): bool
    {
        return self::Html === $this;
    }
}
