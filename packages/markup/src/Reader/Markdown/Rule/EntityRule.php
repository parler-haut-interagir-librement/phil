<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown\Rule;

use Override;
use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Reader\Markdown\InlineParser;

/**
 * An HTML entity — named, decimal or hexadecimal — as the character it stands for; one HTML does not know stays
 * text, as `html_entity_decode()` leaves it.
 *
 * @internal
 */
final readonly class EntityRule implements InlineRule
{
    private const string NAME = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789#';

    #[Override]
    public function triggers(): string
    {
        return '&';
    }

    #[Override]
    public function lex(Cursor $cursor, InlineParser $parser): ?string
    {
        $start = $cursor->position();
        $entity = $cursor->advance().$cursor->consumeWhile(self::NAME);

        if (!$cursor->consume(';')) {
            $cursor->moveTo($start);

            return null;
        }

        return InlineParser::escape(html_entity_decode($entity.';', \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
    }
}
