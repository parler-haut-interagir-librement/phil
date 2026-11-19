<?php

declare(strict_types=1);

namespace Phil\DocumentImport;

/**
 * The result of an export: the bytes of the word processing document, its format, and the report of the blocks and
 * images it has no place for.
 */
final readonly class Exported
{
    public function __construct(
        public Format $format,
        public string $bytes,
        public Report $report,
    ) {
    }

    /**
     * The media type of the document, for the response that serves it.
     */
    public function mediaType(): string
    {
        return Format::Odt === $this->format ? 'application/vnd.oasis.opendocument.text' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    /**
     * The extension of the document's file name, without its dot.
     */
    public function extension(): string
    {
        return $this->format->value;
    }
}
