<?php

declare(strict_types=1);

namespace Phil\Highlight\Scanner;

/**
 * A position in a source, read byte by byte. Every byte of a multibyte UTF-8 character is at or above 0x80, so such
 * characters are read as letters of a word and never split.
 *
 * @internal
 */
final class Cursor
{
    /** The bytes of a word: ASCII letters, digits, `_`, and every byte of a multibyte character. */
    public const string WORD = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_'
        ."\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8A\x8B\x8C\x8D\x8E\x8F\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9A\x9B\x9C\x9D\x9E\x9F"
        ."\xA0\xA1\xA2\xA3\xA4\xA5\xA6\xA7\xA8\xA9\xAA\xAB\xAC\xAD\xAE\xAF\xB0\xB1\xB2\xB3\xB4\xB5\xB6\xB7\xB8\xB9\xBA\xBB\xBC\xBD\xBE\xBF"
        ."\xC0\xC1\xC2\xC3\xC4\xC5\xC6\xC7\xC8\xC9\xCA\xCB\xCC\xCD\xCE\xCF\xD0\xD1\xD2\xD3\xD4\xD5\xD6\xD7\xD8\xD9\xDA\xDB\xDC\xDD\xDE\xDF"
        ."\xE0\xE1\xE2\xE3\xE4\xE5\xE6\xE7\xE8\xE9\xEA\xEB\xEC\xED\xEE\xEF\xF0\xF1\xF2\xF3\xF4\xF5\xF6\xF7\xF8\xF9\xFA\xFB\xFC\xFD\xFE\xFF";

    public const string SPACE = " \t\r\n\f\v";

    public const string DIGITS = '0123456789';

    public const string LETTERS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public int $position = 0;

    public function __construct(
        public readonly string $text,
    ) {
    }

    public function done(): bool
    {
        return !isset($this->text[$this->position]);
    }

    /**
     * The byte `$offset` after the position, or the empty text past the end.
     */
    public function char(int $offset = 0): string
    {
        return $this->text[$this->position + $offset] ?? '';
    }

    /**
     * The byte before the position, or the empty text at the start.
     */
    public function previous(): string
    {
        return 0 === $this->position ? '' : $this->text[$this->position - 1];
    }

    public function at(string $prefix): bool
    {
        return substr($this->text, $this->position, \strlen($prefix)) === $prefix;
    }

    /**
     * Whether the byte `$offset` after the position is one of `$chars`; never past the end.
     */
    public function in(string $chars, int $offset = 0): bool
    {
        return 1 === strspn($this->text, $chars, $this->position + $offset, 1);
    }

    /**
     * Whether only spaces and tabs stand between the start of the line and the position.
     */
    public function atLineStart(): bool
    {
        $start = $this->position;

        while ($start > 0 && str_contains(" \t", $this->text[$start - 1])) {
            --$start;
        }

        return 0 === $start || "\n" === $this->text[$start - 1];
    }

    /**
     * The next `$length` bytes, moving past them.
     */
    public function take(int $length): string
    {
        $text = substr($this->text, $this->position, $length);
        $this->position += \strlen($text);

        return $text;
    }

    /**
     * The bytes from the position that are all among the sets given.
     */
    public function span(string ...$sets): string
    {
        return $this->take(strspn($this->text, implode('', $sets), $this->position));
    }

    /**
     * The byte at the position, and the bytes after it that are all among the sets given.
     */
    public function lead(string ...$sets): string
    {
        return $this->take(1 + strspn($this->text, implode('', $sets), $this->position + 1));
    }

    /**
     * The bytes up to the first of the sets given, or to the end.
     */
    public function until(string ...$sets): string
    {
        return $this->take(strcspn($this->text, implode('', $sets), $this->position));
    }

    /**
     * The byte at the position, and the bytes after it up to the first of the sets given.
     */
    public function leadUntil(string ...$sets): string
    {
        return $this->take(1 + strcspn($this->text, implode('', $sets), $this->position + 1));
    }

    /**
     * The bytes up to the first of `$stops`, or to a comment — one of `$comments`, all also in `$stops`, after a space or
     * a tab or at the position —, or to the end.
     */
    public function untilComment(string $stops, string $comments): string
    {
        $end = $this->position;

        while (true) {
            $end += strcspn($this->text, $stops, $end);

            if (!isset($this->text[$end]) || !str_contains($comments, $this->text[$end]) || str_contains(" \t", $this->text[$end - 1] ?? ' ')) {
                return $this->take($end - $this->position);
            }

            ++$end;
        }
    }

    /**
     * The bytes up to and with `$end`, or to the end when it does not come.
     */
    public function through(string $end): string
    {
        $found = strpos($this->text, $end, $this->position);

        return $this->take(false === $found ? \strlen($this->text) : $found - $this->position + \strlen($end));
    }

    /**
     * A quoted text on one line: the quote at the position, the text up to the same quote not escaped by a backslash,
     * and that quote; at a line feed or the end, what came before it.
     */
    public function quoted(): string
    {
        $quote = $this->char();
        $end = $this->position;

        do {
            $end += 1 + strcspn($this->text, implode('', [$quote, "\\\n"]), $end + 1);
            $escaped = '\\' === ($this->text[$end] ?? '');
            $end += $escaped ? 1 : 0;
        } while ($escaped);

        return $this->take($end - $this->position + ($quote === ($this->text[$end] ?? '') ? 1 : 0));
    }

    /**
     * The rest of the line, without its line feed.
     */
    public function line(): string
    {
        return $this->until("\n");
    }

    /**
     * The line feed at the position and the line after it, without its own line feed.
     */
    public function nextLine(): string
    {
        return $this->leadUntil("\n");
    }
}
