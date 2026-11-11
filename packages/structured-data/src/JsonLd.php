<?php

declare(strict_types=1);

namespace Phil\StructuredData;

use Phil\StructuredData\Exception\InvalidStructuredData;

/**
 * A graph written as JSON-LD, and as the one `<script type="application/ld+json">` element of a page.
 *
 * The JSON cannot close the element nor open a comment in it: `<` and `>` are written `\u003C` and `\u003E`, `&` is
 * written `\u0026`, and the line separators U+2028 and U+2029 are escaped. Text that is not valid UTF-8 is replaced by
 * U+FFFD rather than failing the page.
 */
final class JsonLd
{
    public const int FLAGS = \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_PRESERVE_ZERO_FRACTION;

    private const string NONCE = '/^[A-Za-z0-9+\/_=-]{1,256}$/D';

    public static function encode(Graph $graph, bool $pretty = false): string
    {
        return json_encode($graph->toArray(), $pretty ? self::FLAGS | \JSON_PRETTY_PRINT : self::FLAGS);
    }

    /**
     * The script element of the graph, with the nonce of the page's content security policy when one is given; an empty
     * text for an empty graph.
     *
     * @throws InvalidStructuredData when the nonce is not base64 text of 256 characters at most
     */
    public static function script(Graph $graph, ?string $nonce = null): string
    {
        if (null !== $nonce && 1 !== preg_match(self::NONCE, $nonce)) {
            throw InvalidStructuredData::because('A nonce is base64 text of 1 to 256 characters.');
        }

        if ($graph->isEmpty()) {
            return '';
        }

        return '<script type="application/ld+json"'.(null === $nonce ? '' : ' nonce="'.$nonce.'"').'>'.self::encode($graph).'</script>';
    }
}
