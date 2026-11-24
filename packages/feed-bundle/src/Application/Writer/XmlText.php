<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Application\Writer;

use DateTimeImmutable;
use DateTimeZone;
use XMLWriter;

/**
 * What the XML writers share: a text cleared of the characters XML 1.0 cannot hold, and the dates of each format in
 * UTC.
 *
 * `XMLWriter` escapes `&`, `<` and `>` in a text, and `"` too in an attribute, but writes a control character as it is,
 * which no XML parser reads: such a character, possible in the HTML of an entry, is dropped here.
 *
 * @internal
 */
final readonly class XmlText
{
    private const string NOT_XML = '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u';

    private function __construct()
    {
    }

    public static function clean(string $text): string
    {
        return (string) preg_replace(self::NOT_XML, '', $text);
    }

    /**
     * RFC 3339 in UTC, as Atom and JSON Feed write a date: `2026-11-21T08:30:00Z`.
     */
    public static function rfc3339(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * RFC 822 in UTC, as RSS writes a date: `Sat, 21 Nov 2026 08:30:00 +0000`.
     */
    public static function rfc822(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format(\DATE_RSS);
    }

    public static function open(): XMLWriter
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');

        return $xml;
    }

    /**
     * An element holding a text, cleaned.
     */
    public static function element(XMLWriter $xml, string $name, string $text): void
    {
        $xml->startElement($name);
        $xml->text(self::clean($text));
        $xml->endElement();
    }

    /**
     * An empty element with its attributes, cleaned; a `null` attribute is left out.
     *
     * @param array<string, string|null> $attributes
     */
    public static function empty(XMLWriter $xml, string $name, array $attributes): void
    {
        $xml->startElement($name);
        self::attributes($xml, $attributes);
        $xml->endElement();
    }

    /**
     * @param array<string, string|null> $attributes
     */
    public static function attributes(XMLWriter $xml, array $attributes): void
    {
        foreach ($attributes as $name => $value) {
            if (null !== $value) {
                $xml->writeAttribute($name, self::clean($value));
            }
        }
    }

    /**
     * The document, its elements still open closed.
     */
    public static function close(XMLWriter $xml): string
    {
        $xml->endDocument();

        return $xml->outputMemory();
    }
}
