<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Syntax;

/**
 * The tokenizer of CSS Syntax Level 3, section 4, written from the specification.
 *
 * The input is preprocessed as the specification says — CR LF, CR and FF become LF, NULL becomes U+FFFD — and bytes
 * that are not UTF-8 become U+FFFD. The tokenizer then reads bytes: every byte of a UTF-8 sequence beyond ASCII is
 * at least 0x80, and every code point beyond ASCII is an ident code point, so the specification's code point tests
 * hold on bytes. Comments are dropped. The last token is always `EOF`.
 */
final class Tokenizer
{
    /** What stops a run of plain characters in a string, by its quote. */
    private const array STRING_STOPS = ['"' => "\"\n\\", "'" => "'\n\\"];

    private string $input = '';

    private int $length = 0;

    private int $position = 0;

    private int $line = 1;

    private int $counted = 0;

    /**
     * @return list<Token>
     */
    public function tokenize(string $css): array
    {
        $this->input = self::preprocess($css);
        $this->length = \strlen($this->input);
        $this->position = 0;
        $this->line = 1;
        $this->counted = 0;

        $tokens = [];

        do {
            $token = $this->consumeToken();
            $tokens[] = $token;
        } while (TokenType::Eof !== $token->type);

        return $tokens;
    }

    /** The input stream of the specification, section 3.3, as valid UTF-8. */
    public static function preprocess(string $css): string
    {
        $substitute = mb_substitute_character();
        mb_substitute_character(0xFFFD);

        try {
            return mb_scrub(str_replace(["\r\n", "\r", "\f", "\0"], ["\n", "\n", "\n", "\u{FFFD}"], $css), 'UTF-8');
        } finally {
            mb_substitute_character($substitute);
        }
    }

    private function consumeToken(): Token
    {
        $this->consumeComments();
        $line = $this->lineAt($this->position);

        if ($this->position >= $this->length) {
            return new Token(TokenType::Eof, '', $line);
        }

        $c = $this->input[$this->position];

        if ($this->isWhitespace($c)) {
            $this->position += strspn($this->input, " \t\n", $this->position);

            return new Token(TokenType::Whitespace, ' ', $line);
        }

        $single = match ($c) {
            '(' => TokenType::OpenParenthesis,
            ')' => TokenType::CloseParenthesis,
            '[' => TokenType::OpenSquare,
            ']' => TokenType::CloseSquare,
            '{' => TokenType::OpenCurly,
            '}' => TokenType::CloseCurly,
            ',' => TokenType::Comma,
            ':' => TokenType::Colon,
            ';' => TokenType::Semicolon,
            default => null,
        };

        if (null !== $single) {
            ++$this->position;

            return new Token($single, $c, $line);
        }

        if ('"' === $c || "'" === $c) {
            ++$this->position;

            return $this->consumeString($c, $line);
        }

        if ('#' === $c) {
            if ($this->isIdentChar($this->peek(1)) || $this->isValidEscape($this->peek(1), $this->peek(2))) {
                $isId = $this->startsIdent($this->peek(1), $this->peek(2), $this->peek(3));
                ++$this->position;

                return new Token(TokenType::Hash, $this->consumeIdentSequence(), $line, isId: $isId);
            }
        }

        if ('+' === $c || '.' === $c) {
            return $this->startsNumber($c, $this->peek(1), $this->peek(2)) ? $this->consumeNumeric($line) : $this->delim($c, $line);
        }

        if ('-' === $c) {
            if ($this->startsNumber($c, $this->peek(1), $this->peek(2))) {
                return $this->consumeNumeric($line);
            }

            if ('->' === substr($this->input, $this->position + 1, 2)) {
                $this->position += 3;

                return new Token(TokenType::Cdc, '-->', $line);
            }

            return $this->startsIdent($c, $this->peek(1), $this->peek(2)) ? $this->consumeIdentLike($line) : $this->delim($c, $line);
        }

        if ('<' === $c && '!--' === substr($this->input, $this->position + 1, 3)) {
            $this->position += 4;

            return new Token(TokenType::Cdo, '<!--', $line);
        }

        if ('@' === $c && $this->startsIdent($this->peek(1), $this->peek(2), $this->peek(3))) {
            ++$this->position;

            return new Token(TokenType::AtKeyword, $this->consumeIdentSequence(), $line);
        }

        if ('\\' === $c) {
            return $this->isValidEscape($c, $this->peek(1)) ? $this->consumeIdentLike($line) : $this->delim($c, $line);
        }

        if ($this->isDigit($c)) {
            return $this->consumeNumeric($line);
        }

        if ($this->isIdentStart($c)) {
            return $this->consumeIdentLike($line);
        }

        return $this->delim($c, $line);
    }

    private function delim(string $c, int $line): Token
    {
        ++$this->position;

        return new Token(TokenType::Delim, $c, $line);
    }

    private function consumeComments(): void
    {
        while ('/*' === substr($this->input, $this->position, 2)) {
            $end = strpos($this->input, '*/', $this->position + 2);
            $this->position = false === $end ? $this->length : $end + 2;
        }
    }

    /** Section 4.3.3: a number, then a unit or a percent sign. */
    private function consumeNumeric(int $line): Token
    {
        $start = $this->position;
        $isInteger = true;
        $this->position += '+' === $this->peek(0) || '-' === $this->peek(0) ? 1 : 0;
        $this->position += strspn($this->input, '0123456789', $this->position);

        if ('.' === $this->peek(0) && $this->isDigit($this->peek(1))) {
            $isInteger = false;
            ++$this->position;
            $this->position += strspn($this->input, '0123456789', $this->position);
        }

        $e = $this->peek(0);
        $sign = $this->peek(1);

        if (('e' === $e || 'E' === $e) && ($this->isDigit($sign) || (('+' === $sign || '-' === $sign) && $this->isDigit($this->peek(2))))) {
            $isInteger = false;
            // The letter and its sign or first digit; the digits that follow, whichever it was.
            $this->position += 2;
            $this->position += strspn($this->input, '0123456789', $this->position);
        }

        $representation = substr($this->input, $start, $this->position - $start);

        if ($this->startsIdent($this->peek(0), $this->peek(1), $this->peek(2))) {
            return new Token(TokenType::Dimension, $representation, $line, unit: $this->consumeIdentSequence(), isInteger: $isInteger);
        }

        if ('%' === $this->peek(0)) {
            ++$this->position;

            return new Token(TokenType::Percentage, $representation, $line, isInteger: $isInteger);
        }

        return new Token(TokenType::Number, $representation, $line, isInteger: $isInteger);
    }

    /** Section 4.3.4: an ident, a function, or a URL. */
    private function consumeIdentLike(int $line): Token
    {
        $name = $this->consumeIdentSequence();

        if ('(' !== $this->peek(0)) {
            return new Token(TokenType::Ident, $name, $line);
        }

        ++$this->position;

        if ('url' !== strtolower($name)) {
            return new Token(TokenType::Function, $name, $line);
        }

        while ($this->isWhitespace($this->peek(0)) && $this->isWhitespace($this->peek(1))) {
            ++$this->position;
        }

        $next = $this->isWhitespace($this->peek(0)) ? $this->peek(1) : $this->peek(0);

        if ('"' === $next || "'" === $next) {
            return new Token(TokenType::Function, $name, $line);
        }

        return $this->consumeUrl($line);
    }

    /** Section 4.3.5: a string, or a bad string cut by a newline, which is not consumed. */
    private function consumeString(string $ending, int $line): Token
    {
        $value = '';

        while (true) {
            $run = strcspn($this->input, self::STRING_STOPS[$ending], $this->position);
            $value .= substr($this->input, $this->position, $run);
            $this->position += $run;
            $c = $this->peek(0);

            if ('' === $c || $ending === $c) {
                $this->position += \strlen($c);

                return new Token(TokenType::String, $value, $line);
            }

            if ("\n" === $c) {
                return new Token(TokenType::BadString, $value, $line);
            }

            ++$this->position;
            $next = $this->peek(0);

            if ("\n" === $next) {
                ++$this->position;
            } elseif ('' !== $next) {
                $value .= $this->consumeEscape();
            }
        }
    }

    /** Section 4.3.6: an unquoted URL, or a bad URL whose remnants are consumed. */
    private function consumeUrl(int $line): Token
    {
        $value = '';
        $this->position += strspn($this->input, " \t\n", $this->position);

        while (true) {
            $c = $this->peek(0);

            if ('' === $c || ')' === $c) {
                $this->position += \strlen($c);

                return new Token(TokenType::Url, $value, $line);
            }

            if ($this->isWhitespace($c)) {
                $this->position += strspn($this->input, " \t\n", $this->position);
                $c = $this->peek(0);

                if ('' === $c || ')' === $c) {
                    $this->position += \strlen($c);

                    return new Token(TokenType::Url, $value, $line);
                }

                return $this->consumeBadUrl($value, $line);
            }

            if ('"' === $c || "'" === $c || '(' === $c || $this->isNonPrintable($c)) {
                return $this->consumeBadUrl($value, $line);
            }

            if ('\\' === $c) {
                if (!$this->isValidEscape($c, $this->peek(1))) {
                    return $this->consumeBadUrl($value, $line);
                }

                ++$this->position;
                $value .= $this->consumeEscape();

                continue;
            }

            $value .= $c;
            ++$this->position;
        }
    }

    /** Section 4.3.14: the remnants of a bad URL, up to its closing parenthesis. */
    private function consumeBadUrl(string $value, int $line): Token
    {
        while (true) {
            $c = $this->peek(0);

            if ('' === $c || ')' === $c) {
                $this->position += \strlen($c);

                return new Token(TokenType::BadUrl, $value, $line);
            }

            // An escape is skipped with its backslash, so that an escaped parenthesis does not end the URL; whatever
            // else it holds is skipped as well, one byte after the other.
            $this->position = min($this->position + ('\\' === $c ? 2 : 1), $this->length);
        }
    }

    /** Section 4.3.7: the code point of an escape whose backslash is consumed; U+FFFD at the end of the input. */
    private function consumeEscape(): string
    {
        if ($this->position >= $this->length) {
            return "\u{FFFD}";
        }

        $hex = strspn($this->input, '0123456789abcdefABCDEF', $this->position, 6);

        if (0 === $hex) {
            // One byte: the rest of a character beyond ASCII follows as ident code points, or as the characters of
            // the string or URL being read, and joins it the same way.
            return $this->input[$this->position++];
        }

        $codePoint = \intval(substr($this->input, $this->position, $hex), 16);
        $this->position += $hex;
        $this->position += strspn($this->input, " \t\n", $this->position, 1);

        if (0 === $codePoint || $codePoint > 0x10FFFF || ($codePoint >= 0xD800 && $codePoint <= 0xDFFF)) {
            return "\u{FFFD}";
        }

        return mb_chr($codePoint, 'UTF-8');
    }

    /** Section 4.3.12: the name of an ident, function, at-keyword, hash or unit. */
    private function consumeIdentSequence(): string
    {
        $name = '';

        while (true) {
            $c = $this->peek(0);

            if ($this->isIdentChar($c)) {
                $name .= $c;
                ++$this->position;
            } elseif ($this->isValidEscape($c, $this->peek(1))) {
                ++$this->position;
                $name .= $this->consumeEscape();
            } else {
                return $name;
            }
        }
    }

    private function peek(int $offset): string
    {
        return $this->input[$this->position + $offset] ?? '';
    }

    private function lineAt(int $offset): int
    {
        $this->line += substr_count($this->input, "\n", $this->counted, $offset - $this->counted);
        $this->counted = $offset;

        return $this->line;
    }

    private function isWhitespace(string $c): bool
    {
        return ' ' === $c || "\t" === $c || "\n" === $c;
    }

    private function isDigit(string $c): bool
    {
        return '' !== $c && $c >= '0' && $c <= '9';
    }

    private function isIdentStart(string $c): bool
    {
        return '' !== $c && ('_' === $c || ($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z') || \ord($c[0]) >= 0x80);
    }

    private function isIdentChar(string $c): bool
    {
        return $this->isIdentStart($c) || $this->isDigit($c) || '-' === $c;
    }

    private function isNonPrintable(string $c): bool
    {
        $byte = \ord($c[0]);

        return $byte <= 0x08 || 0x0B === $byte || ($byte >= 0x0E && $byte <= 0x1F) || 0x7F === $byte;
    }

    /** Section 4.3.8: whether two code points start an escape. */
    private function isValidEscape(string $first, string $second): bool
    {
        return '\\' === $first && "\n" !== $second;
    }

    /** Section 4.3.9: whether three code points start an ident sequence. */
    private function startsIdent(string $first, string $second, string $third): bool
    {
        if ('-' === $first) {
            return $this->isIdentStart($second) || '-' === $second || $this->isValidEscape($second, $third);
        }

        return $this->isIdentStart($first) || $this->isValidEscape($first, $second);
    }

    /** Section 4.3.10: whether three code points start a number. */
    private function startsNumber(string $first, string $second, string $third): bool
    {
        if ('+' === $first || '-' === $first) {
            return $this->isDigit($second) || ('.' === $second && $this->isDigit($third));
        }

        if ('.' === $first) {
            return $this->isDigit($second);
        }

        return $this->isDigit($first);
    }
}
