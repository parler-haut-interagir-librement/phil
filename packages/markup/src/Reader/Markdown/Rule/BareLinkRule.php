<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown\Rule;

use Override;
use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Reader\Markdown\InlineParser;

/**
 * The addresses GitHub links without brackets: `https://…`, `http://…` and `www.…`, at the start of a word. The
 * punctuation that ends a sentence, and a closing parenthesis never opened in the address, stay out of it.
 *
 * @internal
 */
final readonly class BareLinkRule implements InlineRule
{
    #[Override]
    public function triggers(): string
    {
        return 'hHwW';
    }

    #[Override]
    public function lex(Cursor $cursor, InlineParser $parser): ?string
    {
        $start = $cursor->position();
        $prefix = strtolower($cursor->advance(8));
        $www = str_starts_with($prefix, 'www.');
        $cursor->moveTo($start);

        if ($parser->inLink() || !str_contains(" \t\n*_~(", $cursor->peek(-1)) || (!$www && !str_starts_with($prefix, 'http://') && 'https://' !== $prefix)) {
            return null;
        }

        $address = rtrim($cursor->consumeUntil(" \t\n<"), '?!.,:*_~\'"');

        while (str_ends_with($address, ')') && substr_count($address, ')') > substr_count($address, '(')) {
            $address = substr($address, 0, -1);
        }

        $cursor->moveTo($start + \strlen($address));

        if ('' === ltrim(substr($address, strcspn($address, '/.')), './')) {
            $cursor->moveTo($start);

            return null;
        }

        return InlineParser::link(($www ? 'https://' : '').$address, InlineParser::escape($address));
    }
}
