<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Infrastructure\Conversion;

use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Exception\InvalidSource;
use Phil\Markup\Level;
use Phil\Markup\MarkupFormat;
use Phil\Markup\Reader\MarkupReader;
use Phil\Markup\Reader\Reading;
use Phil\MarkupBundle\Infrastructure\Format\MarkupFormats;
use Phil\MarkupBundle\Infrastructure\Format\UnavailableFormat;
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Json\JsonEncoder;

/**
 * Any enabled format into UJO: the reader of a format, the document a source reads into, and its JSON — what a block
 * editor opens, what a published version keeps. A source is read at a level, lowered to its format's ceiling: raw HTML
 * written in it becomes a `raw` block at `all` only.
 */
final readonly class UjoConverter
{
    public function __construct(
        private DefaultCompiler $compiler,
        private MarkupFormats $formats,
        private JsonEncoder $encoder = new JsonEncoder(),
    ) {
    }

    /**
     * The reader of a format, the default one for `null`, at a level, the default one for `null`.
     *
     * @throws UnavailableFormat
     */
    public function reader(MarkupFormat|string|null $format = null, Level|string|null $level = null): MarkupReader
    {
        $format = $this->formats->format($format);

        return $this->compiler->reader($format, $this->formats->level($format, $level));
    }

    /**
     * The document and the metadata a source reads into.
     *
     * @throws UnavailableFormat
     * @throws InvalidSource     when a UJO source is not valid
     */
    public function read(string $source, MarkupFormat|string|null $format = null, Level|string|null $level = null): Reading
    {
        return $this->reader($format, $level)->read($source);
    }

    /**
     * The UJO JSON of a source.
     *
     * @throws UnavailableFormat
     * @throws InvalidSource     when a UJO source is not valid
     * @throws InvalidDocument   when a text of the document cannot be written as JSON
     */
    public function toJson(string $source, MarkupFormat|string|null $format = null, Level|string|null $level = null, bool $pretty = false): string
    {
        return $this->encoder->encode($this->read($source, $format, $level)->document, $pretty);
    }
}
