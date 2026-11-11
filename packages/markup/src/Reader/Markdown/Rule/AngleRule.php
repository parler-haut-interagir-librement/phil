<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown\Rule;

use Override;
use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Reader\Markdown\InlineParser;

/**
 * What starts with `<`: an autolink — `<https://…>`, `<scheme:…>`, `<name@example.com>` — as a link; and, when raw
 * HTML is kept, an HTML tag or comment, as written, for the sanitizer to clean. Otherwise the `<` is text.
 *
 * @internal
 */
final readonly class AngleRule implements InlineRule
{
    private const string SCHEME = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789+.-';

    #[Override]
    public function triggers(): string
    {
        return '<';
    }

    #[Override]
    public function lex(Cursor $cursor, InlineParser $parser): ?string
    {
        $start = $cursor->position();
        $cursor->advance();
        $address = $cursor->consumeUntil("<> \t\n");

        if ($cursor->consume('>')) {
            $scheme = strspn($address, self::SCHEME);

            if ($scheme >= 2 && $scheme <= 32 && ':' === ($address[$scheme] ?? '') && ctype_alpha($address[0])) {
                return InlineParser::link($address, InlineParser::escape($address));
            }

            if (false !== filter_var($address, \FILTER_VALIDATE_EMAIL)) {
                return InlineParser::link('mailto:'.$address, InlineParser::escape($address));
            }
        }

        $cursor->moveTo($start);

        return $parser->context->rawHtml ? $this->tag($cursor) : null;
    }

    /**
     * An HTML tag or comment, up to the first `>` outside quotes, consumed as written: what is not a valid tag the
     * sanitizer of `phil/ujo` escapes, and a comment it leaves out.
     */
    private function tag(Cursor $cursor): ?string
    {
        $start = $cursor->position();

        while (!$cursor->isEnd()) {
            $cursor->consumeUntil('>"\'');
            $quote = $cursor->advance();

            if ('>' === $quote) {
                return substr($cursor->text, $start, $cursor->position() - $start);
            }

            if (null === $cursor->consumeThrough($quote)) {
                break;
            }
        }

        $cursor->moveTo($start);

        return null;
    }
}
