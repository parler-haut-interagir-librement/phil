<?php

declare(strict_types=1);

namespace Phil\DocumentImport;

use Phil\DocumentImport\Contract\MediaSource;
use Phil\DocumentImport\Exception\ExportFailed;
use Phil\DocumentImport\Internal\Export\Blocks;
use Phil\DocumentImport\Internal\Export\DocxWriter;
use Phil\DocumentImport\Internal\Export\OdtWriter;
use Phil\DocumentImport\Internal\Zip;
use Phil\SvgSanitizer\SvgSanitizer;
use Phil\Ujo\Document;
use ZipArchive;

/**
 * Exports a UJO document as a word processing document, the way back of the importer: DOCX or ODT, its paragraphs,
 * headings, lists, quotations, code, tables, images, footnotes, links, page breaks and table of contents in the
 * styles a word processor knows, so that the file imports again as the same blocks. The images come from the
 * application's media source; SVG images go into ODT once sanitized and are reported for DOCX. Blocks a word
 * processing document has no place for are reported.
 *
 * The same document gives the same bytes: every entry of the archive carries one fixed date. The archive is written in
 * memory where the PHP version allows it, through a file of the temporary directory otherwise.
 */
final readonly class DocumentExporter
{
    /** The date of every entry, 1 January 1980, the first a ZIP archive can hold. */
    private const int DATE = 315_532_800;

    public function __construct(
        private ?MediaSource $media = null,
        private SvgSanitizer $svg = new SvgSanitizer(),
        private ?string $temporaryDirectory = null,
    ) {
    }

    /**
     * @param array<string, string> $metadata title, subject, creator, keywords, description, language, created and
     *                                        modified — the dates in ISO 8601 —, as an import gives them
     *
     * @throws ExportFailed when the format is not DOCX nor ODT, or the archive cannot be written
     */
    public function export(Document $document, Format $format, array $metadata = []): Exported
    {
        if (Format::Docx !== $format && Format::Odt !== $format) {
            throw ExportFailed::because(\sprintf('A document is exported as DOCX or ODT; "%s" given.', $format->value));
        }

        $report = new Report();
        $notes = Blocks::notes($document);
        $writer = Format::Docx === $format ? new DocxWriter($notes) : new OdtWriter($notes);
        new Blocks($this->media, $this->svg, $report, Format::Odt === $format)->write($document, $writer);

        return new Exported($format, $this->zip($writer->files($metadata)), $report);
    }

    /**
     * @param array<string, string> $files
     *
     * @throws ExportFailed
     */
    private function zip(array $files): string
    {
        return Zip::write(static function (ZipArchive $zip) use ($files): void {
            foreach ($files as $name => $bytes) {
                $zip->addFromString($name, $bytes);
                $zip->setCompressionName($name, 'mimetype' === $name ? ZipArchive::CM_STORE : ZipArchive::CM_DEFLATE);
                $zip->setMtimeName($name, self::DATE);
            }
        }, Zip::inMemory(), $this->temporaryDirectory);
    }
}
