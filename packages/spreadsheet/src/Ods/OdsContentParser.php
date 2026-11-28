<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Ods;

use Generator;
use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\Internal\SafeXmlReader;
use Phil\Spreadsheet\ReadLimits;
use Phil\Spreadsheet\Row;
use XMLReader;

/**
 * Finds the tables of an OpenDocument `content.xml`, node by node: their names, or the rows of one, read by an
 * OdsTableParser. The other tables are skipped, and reading stops at the end of the table asked for.
 *
 * @internal
 */
final readonly class OdsContentParser
{
    public const string TABLE = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';

    public function __construct(private ReadLimits $limits)
    {
    }

    /**
     * @return list<string>
     */
    public function sheetNames(SafeXmlReader $reader): array
    {
        $xml = $reader->xml;
        $names = [];
        $skipDepth = null;

        while ($reader->read()) {
            if (null !== $skipDepth) {
                $skipDepth = self::stillSkipping($xml, $skipDepth);
            } elseif ($this->isTable($xml)) {
                $names[] = (string) $xml->getAttributeNs('name', self::TABLE);
                $skipDepth = $xml->isEmptyElement ? null : $xml->depth;
            }
        }

        return $names;
    }

    /**
     * @param string|null $sheet the table's name, the first table when null
     *
     * @return Generator<int, Row>
     *
     * @throws ReadRefused when the part is unsafe, malformed or over a limit, or has no such table
     */
    public function rows(SafeXmlReader $reader, ?string $sheet, string $part): Generator
    {
        $xml = $reader->xml;
        $skipDepth = null;

        while ($reader->read()) {
            if (null !== $skipDepth) {
                $skipDepth = self::stillSkipping($xml, $skipDepth);
            } elseif ($this->isTable($xml)) {
                if (null !== $sheet && $sheet !== $xml->getAttributeNs('name', self::TABLE)) {
                    $skipDepth = $xml->isEmptyElement ? null : $xml->depth;

                    continue;
                }

                if (!$xml->isEmptyElement) {
                    yield from new OdsTableParser($this->limits, $part)->rows($reader);
                }

                return;
            }
        }

        throw ReadRefused::because(null === $sheet ? 'The spreadsheet has no sheet.' : \sprintf('The spreadsheet has no sheet "%s".', mb_scrub(mb_substr($sheet, 0, 100), 'UTF-8')));
    }

    /**
     * The depth still skipped: null once the end of the element skipped is reached.
     */
    public static function stillSkipping(XMLReader $xml, int $skipDepth): ?int
    {
        return $xml->depth === $skipDepth && XMLReader::END_ELEMENT === $xml->nodeType ? null : $skipDepth;
    }

    private function isTable(XMLReader $xml): bool
    {
        return XMLReader::ELEMENT === $xml->nodeType && 'table' === $xml->localName && self::TABLE === $xml->namespaceURI;
    }
}
