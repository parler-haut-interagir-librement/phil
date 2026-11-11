<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown\Rule;

use Override;
use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Reader\Markdown\InlineParser;

/**
 * A code span: a run of backticks, the text as written, and a run of the same length. Its new lines are spaces, and
 * one space is stripped from each end when both have one. A run never closed stays text.
 *
 * @internal
 */
final readonly class CodeSpanRule implements InlineRule
{
    #[Override]
    public function triggers(): string
    {
        return '`';
    }

    #[Override]
    public function lex(Cursor $cursor, InlineParser $parser): string
    {
        $opening = $cursor->consumeWhile('`');
        $start = $cursor->position();

        while (!$cursor->isEnd()) {
            $cursor->consumeUntil('`');

            if ($cursor->consumeWhile('`') === $opening) {
                $code = str_replace("\n", ' ', substr($cursor->text, $start, $cursor->position() - $start - \strlen($opening)));
                $trim = str_starts_with($code, ' ') && str_ends_with($code, ' ') && '' !== trim($code);

                return '<code>'.InlineParser::escape($trim ? substr($code, 1, -1) : $code).'</code>';
            }
        }

        $cursor->moveTo($start);

        return InlineParser::escape($opening);
    }
}
