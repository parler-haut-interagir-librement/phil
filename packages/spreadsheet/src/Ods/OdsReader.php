<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Ods;

use Generator;
use Override;
use Phil\Spreadsheet\Contract\SpreadsheetReader;
use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\Format;
use Phil\Spreadsheet\Internal\Archive;
use Phil\Spreadsheet\ReadLimits;

/**
 * Reads the rows of an OpenDocument spreadsheet (ODS, or its template), one row at a time: `content.xml` is read node
 * by node from the archive, never extracted, so memory stays that of one row.
 *
 * A string cell is a text, its paragraphs joined by line breaks; a float, percentage or currency cell a number; a
 * boolean a boolean; a date cell a date, or a date and time when it has a time; a time of day the text it shows. The
 * spreadsheet's repeated rows and cells give that many rows and cells, the empty ones at the end left out.
 */
final readonly class OdsReader implements SpreadsheetReader
{
    private const string CONTENT = 'content.xml';

    private const array MEDIA_TYPES = [OdsWriter::MEDIA_TYPE, 'application/vnd.oasis.opendocument.spreadsheet-template'];

    public function __construct(
        private ReadLimits $limits = new ReadLimits(),
    ) {
    }

    #[Override]
    public function format(): Format
    {
        return Format::Ods;
    }

    #[Override]
    public function sheetNames(string $path): array
    {
        $archive = $this->open($path);

        try {
            $reader = $archive->xml(self::CONTENT);

            try {
                return new OdsContentParser($this->limits)->sheetNames($reader);
            } finally {
                $reader->close();
            }
        } finally {
            $archive->close();
        }
    }

    #[Override]
    public function rows(string $path, ?string $sheet = null): Generator
    {
        $archive = $this->open($path);

        try {
            $reader = $archive->xml(self::CONTENT);

            try {
                yield from new OdsContentParser($this->limits)->rows($reader, $sheet, self::CONTENT);
            } finally {
                $reader->close();
            }
        } finally {
            $archive->close();
        }
    }

    private function open(string $path): Archive
    {
        $archive = Archive::open($path, $this->limits);

        try {
            if ($archive->has('mimetype') && !\in_array($archive->text('mimetype', 100), self::MEDIA_TYPES, true)) {
                throw ReadRefused::because('The archive is an OpenDocument file, but not a spreadsheet.');
            }
        } catch (ReadRefused $readRefused) {
            $archive->close();

            throw $readRefused;
        }

        return $archive;
    }
}
