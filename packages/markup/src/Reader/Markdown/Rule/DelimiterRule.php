<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown\Rule;

use Override;
use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Reader\Markdown\Delimiter;
use Phil\Markup\Reader\Markdown\InlineParser;

/**
 * A run of a delimiter character, and whether it may open or close, by the flanking rules of CommonMark: `*` and `_`
 * for emphasis, `~` and `~~` for strikethrough, `==` for highlight; and `^` and `^^` for exponents, `<sup>` and
 * `<sub>`, in a dialect that adds `new DelimiterRule('^')` to its inline rules. A run of a length its character does
 * not take is text.
 */
final readonly class DelimiterRule implements InlineRule
{
    private const array LENGTHS = ['~' => [1, 2], '=' => [2], '^' => [1, 2]];

    public function __construct(
        private string $characters = '*_~=',
    ) {
    }

    #[Override]
    public function triggers(): string
    {
        return $this->characters;
    }

    #[Override]
    public function lex(Cursor $cursor, InlineParser $parser): Delimiter|string
    {
        $character = $cursor->current();
        $before = $cursor->peek(-1);
        $run = $cursor->consumeWhile($character);
        $after = $cursor->current();

        if (!\in_array(\strlen($run), self::LENGTHS[$character] ?? [\strlen($run)], true)) {
            return InlineParser::escape($run);
        }

        $left = !$this->space($after) && (!ctype_punct($after) || $this->space($before) || ctype_punct($before));
        $right = !$this->space($before) && (!ctype_punct($before) || $this->space($after) || ctype_punct($after));

        return '_' === $character
            ? new Delimiter($character, \strlen($run), $left && (!$right || ctype_punct($before)), $right && (!$left || ctype_punct($after)))
            : new Delimiter($character, \strlen($run), $left, $right);
    }

    private function space(string $character): bool
    {
        return '' === $character || ctype_space($character);
    }
}
