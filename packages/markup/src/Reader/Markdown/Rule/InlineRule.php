<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown\Rule;

use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Reader\Markdown\Delimiter;
use Phil\Markup\Reader\Markdown\InlineParser;

/**
 * One kind of inline token: a rule says which characters start it, and lexes it at the cursor. A dialect adds its own
 * rules; a token is HTML, escaped by the rule, or a `Delimiter` the parser pairs into emphasis.
 */
interface InlineRule
{
    /**
     * The characters a token of this rule starts with.
     */
    public function triggers(): string;

    /**
     * The HTML or the delimiter run of the token at the cursor, consumed; `null`, and nothing consumed, when none starts
     * there.
     */
    public function lex(Cursor $cursor, InlineParser $parser): Delimiter|string|null;
}
