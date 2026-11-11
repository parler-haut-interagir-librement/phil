<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown\Rule;

use Override;
use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Reader\Markdown\InlineParser;

/**
 * A backslash: before an ASCII punctuation character, that character as text; before a new line, a line break.
 *
 * @internal
 */
final readonly class EscapeRule implements InlineRule
{
    #[Override]
    public function triggers(): string
    {
        return '\\';
    }

    #[Override]
    public function lex(Cursor $cursor, InlineParser $parser): ?string
    {
        $next = $cursor->peek();

        if ("\n" !== $next && !ctype_punct($next)) {
            return null;
        }

        $cursor->advance(2);

        return "\n" === $next ? '<br>' : InlineParser::escape($next);
    }
}
