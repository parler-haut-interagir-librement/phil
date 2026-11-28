<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Xlsx;

use Generator;
use Override;
use Phil\Spreadsheet\CellType;
use Phil\Spreadsheet\Contract\SpreadsheetReader;
use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\Format;
use Phil\Spreadsheet\Internal\Archive;
use Phil\Spreadsheet\ReadLimits;
use XMLReader;

/**
 * Reads the rows of an Office Open XML (XLSX) workbook, transitional or strict, one row at a time: the worksheet is
 * read node by node from the archive, never extracted, so memory stays that of one row and of the shared texts, which
 * the limits bound.
 *
 * A text is a text, a number a number, a boolean a boolean; a number under a date format is a date, or a date and time
 * when the format shows a time, in UTC to the millisecond. A formula gives the value saved with it. An error such as
 * `#DIV/0!` is read as its text.
 */
final readonly class XlsxReader implements SpreadsheetReader
{
    private const string OFFICE_DOCUMENT = 'officeDocument';

    public function __construct(
        private ReadLimits $limits = new ReadLimits(),
    ) {
    }

    #[Override]
    public function format(): Format
    {
        return Format::Xlsx;
    }

    #[Override]
    public function sheetNames(string $path): array
    {
        $archive = Archive::open($path, $this->limits);

        try {
            return array_keys($this->workbook($archive)['sheets']);
        } finally {
            $archive->close();
        }
    }

    #[Override]
    public function rows(string $path, ?string $sheet = null): Generator
    {
        $archive = Archive::open($path, $this->limits);

        try {
            $workbook = $this->workbook($archive);
            $part = null === $sheet ? reset($workbook['sheets']) : ($workbook['sheets'][$sheet] ?? null);

            if (null === $part || false === $part) {
                throw ReadRefused::because(\sprintf('The workbook has no sheet "%s".', mb_scrub(mb_substr((string) $sheet, 0, 100), 'UTF-8')));
            }

            $parser = new XlsxSheetParser(
                null === $workbook['sharedStrings'] ? [] : $this->sharedStrings($archive, $workbook['sharedStrings']),
                null === $workbook['styles'] ? [] : $this->dateStyles($archive, $workbook['styles']),
                $workbook['date1904'],
                $this->limits,
                $part,
            );
            $reader = $archive->xml($part);

            try {
                yield from $parser->rows($reader);
            } finally {
                $reader->close();
            }
        } finally {
            $archive->close();
        }
    }

    /**
     * The workbook's sheets — their part by their name, in order —, its shared texts, its styles and its date system.
     *
     * @return array{sheets: array<string, string>, sharedStrings: string|null, styles: string|null, date1904: bool}
     */
    private function workbook(Archive $archive): array
    {
        $main = 'xl/workbook.xml';

        if ($archive->has('_rels/.rels')) {
            foreach ($this->relationships($archive, '_rels/.rels', '') as [$type, $target]) {
                if (self::OFFICE_DOCUMENT === $type) {
                    $main = $target;

                    break;
                }
            }
        }

        $reader = $archive->xml($main);
        $declared = [];
        $date1904 = false;

        try {
            while ($reader->read()) {
                if (XMLReader::ELEMENT !== $reader->xml->nodeType) {
                    continue;
                }

                if ('workbookPr' === $reader->xml->localName) {
                    $date1904 = \in_array($reader->attribute('date1904'), ['1', 'true'], true);
                } elseif ('sheet' === $reader->xml->localName) {
                    $declared[] = [(string) $reader->attribute('name'), (string) $reader->attribute('id')];
                }
            }
        } finally {
            $reader->close();
        }

        $folder = \dirname($main);
        $folder = '.' === $folder ? '' : $folder;

        $relationships = $this->relationships($archive, ('' === $folder ? '' : $folder.'/').'_rels/'.basename($main).'.rels', $folder);
        $sheets = [];
        $sharedStrings = null;
        $styles = null;

        foreach ($declared as [$name, $id]) {
            [$type, $target] = $relationships[$id] ?? throw ReadRefused::malformed($main, \sprintf('names the sheet "%s" without its part', mb_scrub(mb_substr($name, 0, 100), 'UTF-8')));

            if ('worksheet' === $type) {
                $sheets[$name] = $target;
            }
        }

        foreach ($relationships as [$type, $target]) {
            if ('sharedStrings' === $type) {
                $sharedStrings = $target;
            } elseif ('styles' === $type) {
                $styles = $target;
            }
        }

        return ['sheets' => $sheets, 'sharedStrings' => $sharedStrings, 'styles' => $styles, 'date1904' => $date1904];
    }

    /**
     * The relationships of a part: the last segment of their type and their target inside the archive, by identifier.
     * A target outside the archive is skipped; one above its root is refused.
     *
     * @return array<string, array{string, string}>
     */
    private function relationships(Archive $archive, string $part, string $folder): array
    {
        if (!$archive->has($part)) {
            return [];
        }

        $reader = $archive->xml($part);
        $relationships = [];

        try {
            while ($reader->read()) {
                if (XMLReader::ELEMENT !== $reader->xml->nodeType || 'Relationship' !== $reader->xml->localName || 'External' === $reader->attribute('TargetMode')) {
                    continue;
                }

                $type = (string) $reader->attribute('Type');
                $slash = strrpos($type, '/');
                $relationships[(string) $reader->attribute('Id')] = [
                    false === $slash ? $type : substr($type, $slash + 1),
                    Archive::resolve($folder, (string) $reader->attribute('Target')),
                ];
            }
        } finally {
            $reader->close();
        }

        return $relationships;
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(Archive $archive, string $part): array
    {
        $reader = $archive->xml($part);
        $xml = $reader->xml;
        $strings = [];
        $current = null;
        $phonetic = 0;
        $bytes = 0;

        try {
            while ($reader->read()) {
                $name = $xml->localName;

                if (XMLReader::ELEMENT === $xml->nodeType) {
                    if ('si' === $name) {
                        $current = '';
                    } elseif ('rPh' === $name && !$xml->isEmptyElement) {
                        ++$phonetic;
                    } elseif ('t' === $name && null !== $current && 0 === $phonetic) {
                        $current .= $reader->text();
                    }
                }

                if ('rPh' === $name && XMLReader::END_ELEMENT === $xml->nodeType) {
                    --$phonetic;
                } elseif ('si' === $name && null !== $current && (XMLReader::END_ELEMENT === $xml->nodeType || $xml->isEmptyElement)) {
                    // A text costs its bytes and the memory of a PHP string besides.
                    $bytes += \strlen($current) + 32;

                    if ($bytes > $this->limits->maxSharedStringsBytes) {
                        throw ReadRefused::overLimit('The memory of the shared texts', $this->limits->maxSharedStringsBytes);
                    }

                    $strings[] = XlsxText::unescape($current);
                    $current = null;
                }
            }
        } finally {
            $reader->close();
        }

        return $strings;
    }

    /**
     * The kind of date of each cell style whose number format shows one.
     *
     * @return array<int, CellType>
     */
    private function dateStyles(Archive $archive, string $part): array
    {
        $reader = $archive->xml($part);
        $xml = $reader->xml;
        /** @var array<int, string> $codes */
        $codes = [];
        $kinds = [];
        $index = 0;
        $inCellFormats = false;

        try {
            while ($reader->read()) {
                if (XMLReader::END_ELEMENT === $xml->nodeType && 'cellXfs' === $xml->localName) {
                    $inCellFormats = false;
                }

                if (XMLReader::ELEMENT !== $xml->nodeType) {
                    continue;
                }

                if ('numFmt' === $xml->localName) {
                    $codes[(int) $xml->getAttribute('numFmtId')] = (string) $xml->getAttribute('formatCode');
                } elseif ('cellXfs' === $xml->localName) {
                    $inCellFormats = !$xml->isEmptyElement;
                } elseif ($inCellFormats && 'xf' === $xml->localName) {
                    $id = (int) $xml->getAttribute('numFmtId');
                    $kind = NumberFormats::kind($id, $codes[$id] ?? null);

                    if ($kind instanceof CellType) {
                        $kinds[$index] = $kind;
                    }

                    ++$index;
                }
            }
        } finally {
            $reader->close();
        }

        return $kinds;
    }
}
