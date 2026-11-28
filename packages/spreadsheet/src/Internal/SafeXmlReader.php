<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Internal;

use Phil\Spreadsheet\Exception\ReadRefused;
use XMLReader;

/**
 * An XML part read node by node, safely: a part that declares a document type is refused before anything is
 * expanded, so that no external entity is loaded (XXE) and no entity multiplies (billion laughs); no network access;
 * the bytes go through a budget; and a part that ends before its root element closes is refused as malformed.
 *
 * @internal
 */
final class SafeXmlReader
{
    private const string XMLNS = 'http://www.w3.org/2000/xmlns/';

    public readonly XMLReader $xml;

    private bool $complete = false;

    /**
     * @param resource $stream
     */
    public function __construct(
        private readonly mixed $stream,
        private readonly ByteBudget $budget,
        private readonly string $part,
        private readonly int $limit,
    ) {
        // The warnings of libxml are not the caller's business: a part that cannot be read is refused by read().
        $this->xml = @XMLReader::fromStream($stream, null, \LIBXML_NONET | \LIBXML_COMPACT);
    }

    /**
     * A reader of XML held in memory, within a budget of bytes.
     */
    public static function fromString(string $xml, string $part, int $limit): self
    {
        $stream = fopen('php://memory', 'w+');
        \assert(false !== $stream);
        fwrite($stream, $xml);
        rewind($stream);
        $budget = new ByteBudget($limit);
        BudgetFilter::attach($stream, $budget);

        return new self($stream, $budget, $part, $limit);
    }

    /**
     * Moves to the next node.
     *
     * @return bool false at the end of the part
     *
     * @throws ReadRefused when the part declares a document type, goes over its budget or is not well formed
     */
    public function read(): bool
    {
        $xml = $this->xml;

        if (@$xml->read()) {
            // The properties of XMLReader are costly: each is read only when the one before leaves the question open.
            $type = $xml->nodeType;

            if (XMLReader::END_ELEMENT === $type || (XMLReader::ELEMENT === $type && $xml->isEmptyElement)) {
                $this->complete = 0 === $xml->depth;
            } elseif (XMLReader::DOC_TYPE === $type) {
                throw ReadRefused::malformed($this->part, 'declares a document type, which a spreadsheet never needs');
            }

            return true;
        }

        if ($this->budget->exceeded) {
            throw ReadRefused::overLimit(\sprintf('The size of the part "%s"', $this->part), $this->limit);
        }

        if (!$this->complete) {
            throw ReadRefused::malformed($this->part);
        }

        return false;
    }

    /**
     * The text inside the current element, read without moving.
     */
    public function text(): string
    {
        return @$this->xml->readString();
    }

    /**
     * The value of the current element's attribute of that local name, whatever its namespace: the transitional and
     * the strict schemas of the format put the same attributes in different namespaces.
     */
    public function attribute(string $localName): ?string
    {
        $xml = $this->xml;
        $value = null;

        if ($xml->moveToFirstAttribute()) {
            do {
                if ($xml->localName === $localName && self::XMLNS !== $xml->namespaceURI) {
                    $value = $xml->value;

                    break;
                }
            } while ($xml->moveToNextAttribute());

            $xml->moveToElement();
        }

        return $value;
    }

    public function close(): void
    {
        $this->xml->close();

        if (\is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}
