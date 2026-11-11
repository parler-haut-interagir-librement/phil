<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Internal;

use Closure;
use Phil\Ujo\Block;

/**
 * The canonical form of a reference in sanitized HTML, `<sup data-footnote="id">…</sup>`, found by one pass over the
 * text, and the other rules every part of the library reads a document by.
 *
 * @internal
 */
final class ReferenceMarkup
{
    /** The block types whose text is not HTML: what looks like a reference in them is shown, not followed. */
    public const array LITERAL_TYPES = ['code' => true, 'mermaid' => true, 'math' => true];

    private const string START = '/<sup data-footnote="([A-Za-z0-9_-]{1,64})">/';

    /**
     * The text with each reference replaced by what `$write` gives for its note and for whether the reference sits
     * inside a link. An element is matched to its own end tag, however many `sup` it holds; one never closed runs to
     * the end of the text.
     *
     * @param Closure(string, bool): string $write
     */
    public static function replace(string $html, Closure $write): string
    {
        $written = '';
        $at = 0;
        $links = 0;

        while (1 === preg_match(self::START, $html, $match, \PREG_OFFSET_CAPTURE, $at)) {
            $start = $match[0][1];
            $before = substr($html, $at, $start - $at);
            $links += substr_count($before, '<a ') - substr_count($before, '</a>');
            $written .= $before.$write($match[1][0], $links > 0);
            $at = self::end($html, $start + \strlen($match[0][0]));
        }

        return $written.substr($html, $at);
    }

    /**
     * The identifiers the references of a text name, in their order.
     *
     * @return list<string>
     */
    public static function ids(string $html): array
    {
        $ids = [];

        self::replace($html, static function (string $id) use (&$ids): string {
            $ids[] = $id;

            return '';
        });

        return $ids;
    }

    /**
     * The text without its references.
     */
    public static function strip(string $html): string
    {
        return self::replace($html, static fn (): string => '');
    }

    /**
     * The text on one line: each run of ASCII white space a single space, none at the ends. A no-break space, which
     * typography puts on purpose, stays.
     */
    public static function collapse(string $text): string
    {
        return implode(' ', array_filter(explode(' ', strtr($text, "\t\r\n\f", '    ')), static fn (string $word): bool => '' !== $word));
    }

    /**
     * Whether a block is a heading of a level or above.
     */
    public static function isHeading(Block $block, int $level): bool
    {
        $heading = $block->data['level'] ?? null;

        return 'header' === $block->type && \is_int($heading) && $heading <= $level;
    }

    /**
     * The offset just after the end tag of the element whose content starts at `$from`.
     */
    private static function end(string $html, int $from): int
    {
        $depth = 1;
        $at = $from;

        while (1 === preg_match('~<(/?)sup\b[^>]*>~', $html, $tag, \PREG_OFFSET_CAPTURE, $at)) {
            $at = $tag[0][1] + \strlen($tag[0][0]);
            $depth += '' === $tag[1][0] ? 1 : -1;

            if (0 === $depth) {
                return $at;
            }
        }

        return \strlen($html);
    }
}
