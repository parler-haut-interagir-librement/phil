<?php

declare(strict_types=1);

namespace Phil\Highlight;

/**
 * Writes tokens as HTML: each text escaped, each typed token in a `<span>` of its class. The output holds no other
 * markup, so it can go inside `<pre><code>` as it is; invalid UTF-8 is replaced by U+FFFD.
 *
 * `formatLines()` writes the same tokens line by line, each line in a `<span class="hl-line">` with its marks and,
 * when asked, its number; the lines are joined by the newlines of the source.
 */
final class HtmlFormatter
{
    /**
     * An annotation of a comment, with the spaces before it: `[!code ++]`, `[!code --]`, `[!code highlight]`, each
     * optionally followed by `:` and the number of lines it marks, its own included.
     */
    private const string ANNOTATION = '/[ \t]*\[!code (\+\+|--|highlight)(?::(\d{1,4}))?\]/';

    /**
     * What a comment holds once its annotations are gone, when nothing else was written in it: its delimiters alone.
     */
    private const string EMPTY_COMMENT = '~^\s*(?://+|#+|--|;+|%+|/\*+|<!--|\{#-?|\(\*)?\s*(?:\*+/|-->|-?#\}|\*\))?\s*$~D';

    /**
     * @param list<Token> $tokens
     */
    public static function format(array $tokens): string
    {
        $html = '';

        foreach ($tokens as $token) {
            $text = htmlspecialchars($token->text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $html .= null === $token->type ? $text : '<span class="'.$token->type->className().'">'.$text.'</span>';
        }

        return $html;
    }

    /**
     * The tokens line by line: `<span class="hl-line">`, then ` hl-line-highlighted`, ` hl-line-inserted` and
     * ` hl-line-deleted` for its marks, and inside it `<span class="hl-line-number">` when the lines are numbered. A
     * newline that ends the source ends the last line; it does not open an empty one.
     *
     * @param list<Token> $tokens
     */
    public static function formatLines(array $tokens, LineOptions $options): string
    {
        $lines = self::split($tokens);
        $trailingNewline = \count($lines) > 1 && [] === $lines[\count($lines) - 1];

        if ($trailingNewline) {
            array_pop($lines);
        }

        [$lines, $marks] = $options->annotations ? self::annotate($lines) : [$lines, []];
        $html = [];

        foreach ($lines as $index => $line) {
            if ($options->highlighted->contains($index + 1)) {
                $marks[$index][LineMark::Highlighted->value] = true;
            }

            $number = null === $options->firstNumber ? '' : '<span class="hl-line-number">'.($options->firstNumber + $index).'</span>';
            $html[] = '<span class="'.self::classes($marks[$index] ?? []).'">'.$number.self::format($line).'</span>';
        }

        return implode("\n", $html).($trailingNewline ? "\n" : '');
    }

    /**
     * The lines with the annotations of their comments taken out, and the marks of each line by its index. One pass:
     * each mark holds until the end of the farthest annotation read so far, so the cost does not grow with the number
     * of lines an annotation covers.
     *
     * @param list<list<Token>> $lines
     *
     * @return array{list<list<Token>>, array<int, array<string, true>>}
     */
    private static function annotate(array $lines): array
    {
        $marks = [];
        $until = [];

        foreach ($lines as $index => $line) {
            [$lines[$index], $found] = self::annotations($line);

            foreach ($found as [$mark, $length]) {
                $until[$mark->value] = max($until[$mark->value] ?? 0, $index + $length);
            }

            foreach ($until as $mark => $end) {
                if ($index < $end) {
                    $marks[$index][$mark] = true;
                }
            }
        }

        return [$lines, $marks];
    }

    /**
     * `hl-line` and the class of each mark, in the order of `LineMark`.
     *
     * @param array<string, true> $marks
     */
    private static function classes(array $marks): string
    {
        $classes = 'hl-line';

        foreach (LineMark::cases() as $mark) {
            if ($marks[$mark->value] ?? false) {
                $classes .= ' '.$mark->className();
            }
        }

        return $classes;
    }

    /**
     * The tokens cut at each newline, the newlines dropped; a token across lines gives a piece of the same type on
     * each.
     *
     * @param list<Token> $tokens
     *
     * @return non-empty-list<list<Token>>
     */
    private static function split(array $tokens): array
    {
        $lines = [];
        $line = [];

        foreach ($tokens as $token) {
            foreach (explode("\n", $token->text) as $piece => $text) {
                if ($piece > 0) {
                    $lines[] = $line;
                    $line = [];
                }

                if ('' !== $text) {
                    $line[] = new Token($text, $token->type);
                }
            }
        }

        $lines[] = $line;

        return $lines;
    }

    /**
     * The tokens of one line with the annotations of its comments taken out, and the marks they name with the number
     * of lines each covers. A comment left with nothing but its delimiters goes, with the spaces before it.
     *
     * @param list<Token> $line
     *
     * @return array{list<Token>, list<array{LineMark, int<1, max>}>}
     */
    private static function annotations(array $line): array
    {
        $kept = [];
        $found = [];

        foreach ($line as $token) {
            if (TokenType::Comment !== $token->type || 0 === preg_match_all(self::ANNOTATION, $token->text, $matches, \PREG_SET_ORDER)) {
                $kept[] = $token;

                continue;
            }

            foreach ($matches as $match) {
                $mark = LineMark::fromAnnotation($match[1]);

                if ($mark instanceof LineMark) {
                    $found[] = [$mark, isset($match[2]) ? max(1, (int) $match[2]) : 1];
                }
            }

            $text = (string) preg_replace(self::ANNOTATION, '', $token->text);

            if (1 !== preg_match(self::EMPTY_COMMENT, $text)) {
                $kept[] = new Token($text, TokenType::Comment);

                continue;
            }

            $kept = self::trimEnd($kept);
        }

        return [$kept, $found];
    }

    /**
     * The tokens without the spaces and tabs that end them, when those are plain text.
     *
     * @param list<Token> $tokens
     *
     * @return list<Token>
     */
    private static function trimEnd(array $tokens): array
    {
        while ([] !== $tokens) {
            $last = $tokens[\count($tokens) - 1];

            if (null !== $last->type) {
                break;
            }

            $text = rtrim($last->text, " \t");
            array_pop($tokens);

            if ('' !== $text) {
                $tokens[] = new Token($text);

                break;
            }
        }

        return $tokens;
    }
}
