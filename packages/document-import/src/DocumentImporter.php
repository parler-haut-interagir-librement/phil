<?php

declare(strict_types=1);

namespace Phil\DocumentImport;

use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Exception\UnsupportedFormat;
use Phil\DocumentImport\Internal\Archive;
use Phil\DocumentImport\Internal\Builder;
use Phil\DocumentImport\Internal\Docx\DocxReader;
use Phil\DocumentImport\Internal\HtmlPreparation;
use Phil\DocumentImport\Internal\Odt\OdtReader;
use Phil\DocumentImport\Internal\Pdf\PdfReader;
use Phil\DocumentImport\Internal\Rtf\RtfReader;
use Phil\Markup\Exception\InvalidSource;
use Phil\Markup\Reader\Html\HtmlReader;
use Phil\Markup\Reader\Markdown\MarkdownReader;
use Phil\Markup\Reader\MarkupReader;
use Phil\SvgSanitizer\SvgSanitizer;
use Phil\Ujo\Exception\InvalidDocument as InvalidUjo;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Normalizer;

/**
 * Imports a file into a UJO document: a word processing document of Office Open XML, OpenDocument — packaged or
 * flat — or RTF, and the text of a PDF, read by this library; Markdown and HTML read by `phil/markup`. The paragraph
 * styles go through the application's style map first. The images are given to the media store, SVG images once
 * sanitized by `phil/svg-sanitizer`, and the report says what the document had that a UJO document has no place for.
 */
final readonly class DocumentImporter
{
    public function __construct(
        private MediaStore $media = new MediaFolder(),
        private ?MarkupReader $markdown = null,
        private ?MarkupReader $html = null,
        private ?Normalizer $normalizer = null,
        private Limits $limits = new Limits(),
        private SvgSanitizer $svg = new SvgSanitizer(),
        private StyleMap $styles = new StyleMap(),
    ) {
    }

    /**
     * The file at a path, its format given or detected from its name and bytes.
     *
     * @throws InvalidDocument   when the file cannot be read or is not what its format says
     * @throws UnsupportedFormat when the format is not given and cannot be detected
     * @throws LimitExceeded     when an archive is larger than the limits once uncompressed
     * @throws InvalidSource     when the Markdown or HTML reader given refuses the source
     * @throws InvalidUjo        when the media store answers an address an image block refuses
     * @throws UnknownBlockType  when the normalizer given does not know a block type the import writes
     */
    public function importFile(string $path, ?Format $format = null): Imported
    {
        $contents = is_file($path) ? file_get_contents($path) : false;

        if (false === $contents) {
            throw InvalidDocument::because(\sprintf('The file "%s" cannot be read.', $path));
        }

        return $this->import($contents, $format, basename($path));
    }

    /**
     * The contents of a file, its format given or detected from its name and bytes.
     *
     * @throws InvalidDocument   when the file is not what its format says
     * @throws UnsupportedFormat when the format is not given and cannot be detected
     * @throws LimitExceeded     when a document is larger, or nests deeper, than the limits
     * @throws InvalidSource     when the Markdown or HTML reader given refuses the source
     * @throws InvalidUjo        when the media store answers an address an image block refuses
     * @throws UnknownBlockType  when the normalizer given does not know a block type the import writes
     */
    public function import(string $contents, ?Format $format = null, ?string $filename = null): Imported
    {
        Format::refuseLegacy($contents);
        $format ??= Format::detect($contents, $filename);
        $out = new Builder($this->media, $this->svg, $this->styles);
        $this->checkSize($contents, Format::Fodt === $format ? $this->limits->entryBytes : $this->limits->totalBytes);

        if (Format::Markdown === $format || Format::Html === $format) {
            $reading = Format::Markdown === $format
                ? ($this->markdown ?? new MarkdownReader())->read($contents)
                : ($this->html ?? new HtmlReader())->read(HtmlPreparation::prepare($contents, $out));

            return new Imported($format, $reading->document, $reading->metadata, $out->media, $out->report);
        }

        $metadata = match ($format) {
            Format::Docx => new DocxReader(Archive::open($contents, $this->limits), $out)->read(),
            Format::Odt => new OdtReader(Archive::open($contents, $this->limits), $out)->read(),
            Format::Fodt => new OdtReader(Archive::of(['content.xml' => $contents]), $out)->readFlat(),
            Format::Rtf => new RtfReader($contents, $out, $this->limits)->read(),
            default => new PdfReader($contents, $out, $this->limits)->read(),
        };

        return new Imported($format, ($this->normalizer ?? new Normalizer())->normalize($out->document()), $metadata, $out->media, $out->report);
    }

    /**
     * @throws LimitExceeded
     */
    private function checkSize(string $contents, int $bytes): void
    {
        if (\strlen($contents) > $bytes) {
            throw LimitExceeded::because(\sprintf('The file is larger than %d bytes.', $bytes));
        }
    }
}
