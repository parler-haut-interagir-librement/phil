<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Application;

use Phil\DocumentImport\DocumentExporter;
use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Exception\ExportFailed;
use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Exception\UnsupportedFormat;
use Phil\DocumentImport\Exported;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Imported;
use Phil\DocumentImport\Limits;
use Phil\DocumentImportBundle\Application\Contract\DocumentScreening;
use Phil\DocumentImportBundle\Domain\Event\DocumentImported;
use Phil\Ujo\Document;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The importer and the exporter as the application configured them: a file whose format the application does not
 * accept is refused before a reader opens it, an uploaded file larger than the limits before it is read in memory, a
 * file the screening — the antivirus — refuses before it is parsed, and every import dispatches `DocumentImported`
 * with its report, when there is a dispatcher. The report is added to the document as a block under the configured
 * title on demand.
 *
 * It holds no state between calls: one service imports every file.
 */
final readonly class DocumentImports
{
    /**
     * @param list<Format> $imports the formats an import reads
     * @param list<Format> $exports the formats an export writes, DOCX and ODT at most
     */
    public function __construct(
        private DocumentImporter $importer,
        private DocumentExporter $exporter,
        private Limits $limits = new Limits(),
        private array $imports = [Format::Docx, Format::Odt, Format::Fodt, Format::Rtf, Format::Pdf, Format::Markdown, Format::Html],
        private array $exports = [Format::Docx, Format::Odt],
        private string $reportTitle = 'What was not imported',
        private ?EventDispatcherInterface $events = null,
        private ?DocumentScreening $screening = null,
    ) {
    }

    /**
     * A file on disk — an upload, whose client name is given apart since its temporary path has no extension —,
     * refused before it is read when it is larger than the limits.
     *
     * @throws InvalidDocument   when the file cannot be read or is not what its format says
     * @throws UnsupportedFormat when its format is not accepted, or cannot be detected
     * @throws LimitExceeded     when the file, or what it holds, is larger than the limits
     */
    public function importFile(string $path, ?Format $format = null, ?string $filename = null): Imported
    {
        $size = is_file($path) && is_readable($path) ? filesize($path) : false;

        if (false === $size) {
            throw InvalidDocument::because(\sprintf('The file "%s" cannot be read.', basename($path)));
        }

        if ($size > $this->limits->totalBytes) {
            throw LimitExceeded::because(\sprintf('The file "%s" is larger than %d bytes.', basename($path), $this->limits->totalBytes));
        }

        $contents = file_get_contents($path);

        if (false === $contents) {
            throw InvalidDocument::because(\sprintf('The file "%s" cannot be read.', basename($path)));
        }

        return $this->import($contents, $format, $filename ?? basename($path));
    }

    /**
     * The bytes of a file, its format given or detected from its name and bytes; Markdown and HTML included, every
     * format is refused when larger than the limits, and the file goes through the screening before it is parsed.
     *
     * @param string|null $claim the claim of a file imported on a worker, given to `DocumentImported`
     *
     * @throws InvalidDocument   when the file is not what its format says, or the screening refuses it
     * @throws UnsupportedFormat when its format is not accepted, or cannot be detected
     * @throws LimitExceeded     when the file, or what it holds, is larger than the limits
     */
    public function import(string $contents, ?Format $format = null, ?string $filename = null, ?string $claim = null): Imported
    {
        $format = $this->accept($contents, $format, $filename);
        $this->screening?->screen($contents, $filename ?? 'document');

        $imported = $this->importer->import($contents, $format, $filename);
        $this->events?->dispatch(new DocumentImported($imported, $filename, $claim));

        return $imported;
    }

    /**
     * The format of a file the application accepts, given or detected, before anything reads it: what refuses a file
     * at once, before it waits for a worker.
     *
     * @throws UnsupportedFormat when its format is not accepted, or cannot be detected
     * @throws LimitExceeded     when the file is larger than the limits
     */
    public function accept(string $contents, ?Format $format = null, ?string $filename = null): Format
    {
        if (\strlen($contents) > $this->limits->totalBytes) {
            throw LimitExceeded::because(\sprintf('The document is larger than %d bytes.', $this->limits->totalBytes));
        }

        $format ??= Format::detect($contents, $filename);

        if (!\in_array($format, $this->imports, true)) {
            throw UnsupportedFormat::because(\sprintf('A %s file is not accepted here; the formats accepted are %s.', strtoupper($format->value), $this->names($this->imports)));
        }

        return $format;
    }

    /**
     * The title the report of an import is shown under.
     */
    public function reportTitle(): string
    {
        return $this->reportTitle;
    }

    /**
     * The document of an import with its report as a last `warning` block, under the configured title; the document
     * alone when nothing was left out.
     */
    public function documentWithReport(Imported $imported): Document
    {
        return $imported->documentWithReport($this->reportTitle);
    }

    /**
     * A UJO document as a word processing document of an accepted format.
     *
     * @param array<string, string> $metadata as an import gives them
     *
     * @throws ExportFailed when the format is not accepted, or the archive cannot be written
     */
    public function export(Document $document, Format $format, array $metadata = []): Exported
    {
        if (!\in_array($format, $this->exports, true)) {
            throw ExportFailed::because(\sprintf('A document is not exported as %s here; the formats accepted are %s.', strtoupper($format->value), $this->names($this->exports)));
        }

        return $this->exporter->export($document, $format, $metadata);
    }

    /**
     * @param list<Format> $formats
     */
    private function names(array $formats): string
    {
        return [] === $formats ? 'none' : implode(', ', array_map(static fn (Format $format): string => strtoupper($format->value), $formats));
    }
}
