<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown\Rule;

use Override;
use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Reader\Markdown\InlineParser;

/**
 * A ruby, `{漢字|かんじ}`: a base and its reading, each up to 100 characters on the line, neither holding a brace or a
 * bar, written as `<ruby>` with its reading between the parentheses of `<rp>`.
 *
 * @internal
 */
final readonly class RubyRule implements InlineRule
{
    private const string PATTERN = '/\G\{([^{}|\n]{1,400})\|([^{}|\n]{1,400})\}/u';

    #[Override]
    public function triggers(): string
    {
        return '{';
    }

    #[Override]
    public function lex(Cursor $cursor, InlineParser $parser): ?string
    {
        if (1 !== preg_match(self::PATTERN, $cursor->text, $match, 0, $cursor->position())
            || '' === trim($match[1]) || '' === trim($match[2]) || mb_strlen($match[1]) > 100 || mb_strlen($match[2]) > 100) {
            return null;
        }

        $cursor->advance(\strlen($match[0]));

        return self::ruby(InlineParser::escape($match[1]), InlineParser::escape($match[2]));
    }

    /**
     * A base and its reading, both HTML already, as a ruby; the base alone when the reading is blank.
     */
    public static function ruby(string $base, string $reading): string
    {
        return '' === trim($reading) ? $base : '<ruby>'.$base.'<rp>(</rp><rt>'.$reading.'</rt><rp>)</rp></ruby>';
    }
}
