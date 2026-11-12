<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\ValueObject;

use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\Markup\MarkupFormat;

/**
 * The main text of a row: its source, the name of the format the source is written in — a format of `phil/markup`, or
 * of an add-on —, and the HTML compiled from it.
 *
 * **The source is the truth and the HTML is derived from it**: the data mapper compiles the source, then
 * purifies the result, and builds the body from the three; a publication converts the source into UJO and
 * compiles the HTML from that UJO. The HTML is kept so a page is not compiled on
 * every read; it is purified again before it is shown (see `BodyCapable`).
 *
 * The source is taken as written, leading spaces included, since they mean something in Markdown; it must
 * only hold something other than whitespace, and be valid UTF-8 like the HTML.
 */
final readonly class Body
{
    private function __construct(
        public string $source,
        public string $format,
        public string $html,
    ) {
    }

    /**
     * @throws InvalidContent
     */
    public static function of(string $source, MarkupFormat|string $format, string $html): self
    {
        if ('' === trim($source)) {
            throw InvalidContent::because('A body holds something other than whitespace; an absent body is null.');
        }

        if (1 !== preg_match('//u', $source) || 1 !== preg_match('//u', $html)) {
            throw InvalidContent::because('A body is valid UTF-8, its source as its HTML.');
        }

        return new self($source, MarkupFormat::nameOf($format), $html);
    }
}
