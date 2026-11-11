<?php

declare(strict_types=1);

namespace Phil\Markup;

/**
 * The languages the library reads. Every one ends up as HTML, which is purified before it is shown; the formats with a
 * reader pass through a UJO document on the way. HTML, compiled as written, has a reader too, which converts it into
 * UJO. A format is known by its name, the value of its case; an add-on brings a format of its own under another name,
 * which the compilers take as a string.
 */
enum MarkupFormat: string
{
    /**
     * CommonMark, with the GitHub extensions, footnotes, attributes, definition lists, front matter, `:::` and `{{ }}`
     * blocks, and pages and columns.
     */
    case Markdown = 'markdown';

    /** HTML, taken as written. */
    case Html = 'html';

    /** BBCode, the tags of forums: [b], [url], [quote], [list]… */
    case BBCode = 'bbcode';

    /** The wikitext of MediaWiki: `== headings ==`, `'''bold'''`, `[[links]]`, `{| tables |}`, `<ref>`, templates… */
    case MediaWiki = 'mediawiki';

    /** The syntax of DokuWiki: `====== headings ======`, `**bold**`, `//italic//`, `[[links]]`, `((footnotes))`… */
    case DokuWiki = 'dokuwiki';

    /** Creole 1.0 and its additions: `= headings =`, `**bold**`, `//italic//`, `[[links]]`, `{{images}}`, `<<placeholders>>`… */
    case Creole = 'creole';

    /** Plain text: escaped, its paragraphs and line breaks kept, its addresses made links. */
    case Text = 'text';

    /** UJO (Universal JSON Output), the block document format, read and checked by `phil/ujo`. */
    case Ujo = 'ujo';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $format): string => $format->value, self::cases());
    }

    /**
     * The name of a format: the value of a case, or the string of a format an add-on brings.
     */
    public static function nameOf(self|string $format): string
    {
        return $format instanceof self ? $format->value : $format;
    }
}
