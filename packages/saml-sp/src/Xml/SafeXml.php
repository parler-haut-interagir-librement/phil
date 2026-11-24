<?php

declare(strict_types=1);

namespace Phil\SamlSp\Xml;

use DOMDocument;
use DOMElement;
use DOMNode;
use Phil\SamlSp\Exception\InvalidXml;

/**
 * Reads a document from the outside into DOM, refusing before parsing what SAML never needs and attacks use: more bytes
 * than the budget, a byte that is not UTF-8, a NUL, an encoding other than UTF-8, a DOCTYPE or an entity declaration
 * (no external entity, no expansion bomb). After parsing, it refuses a processing instruction or an entity reference,
 * and walks the tree to bound its depth and its nodes. The network is never reached.
 */
final class SafeXml
{
    private function __construct()
    {
    }

    public static function load(string $xml, XmlBudget $budget): DOMDocument
    {
        self::screen($xml, $budget);

        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument();
            $loaded = $document->loadXML($xml, \LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$loaded || !$document->documentElement instanceof DOMElement) {
            throw InvalidXml::because('The document is not well-formed XML.');
        }

        self::walk($document, $budget);

        return $document;
    }

    /**
     * @phpstan-assert non-empty-string $xml
     */
    private static function screen(string $xml, XmlBudget $budget): void
    {
        if ('' === $xml || \strlen($xml) > $budget->maxBytes) {
            throw InvalidXml::because(\sprintf('A document has 1 to %d bytes.', $budget->maxBytes));
        }

        if (str_contains($xml, "\0") || 1 !== preg_match('//u', $xml)) {
            throw InvalidXml::because('A document is UTF-8 text.');
        }

        if (1 === preg_match('/^\s*<\?xml[^>]*\bencoding\s*=\s*["\'](?!utf-8["\'])/i', $xml)) {
            throw InvalidXml::because('A document is encoded in UTF-8.');
        }

        if (1 === preg_match('/<!(?:DOCTYPE|ENTITY)/i', $xml)) {
            throw InvalidXml::because('A document with a DOCTYPE or an entity declaration is refused.');
        }
    }

    private static function walk(DOMDocument $document, XmlBudget $budget): void
    {
        $nodes = 0;
        /** @var list<array{DOMNode, int}> $stack */
        $stack = [[$document, 0]];

        while ([] !== $stack) {
            [$node, $depth] = array_pop($stack);

            if ($depth > $budget->maxDepth) {
                throw InvalidXml::because(\sprintf('A document is %d levels deep at most.', $budget->maxDepth));
            }

            foreach ($node->childNodes as $child) {
                if (\in_array($child->nodeType, [\XML_PI_NODE, \XML_ENTITY_REF_NODE, \XML_DOCUMENT_TYPE_NODE, \XML_ENTITY_NODE], true)) {
                    throw InvalidXml::because('A document with a processing instruction or an entity is refused.');
                }

                $nodes += 1 + ($child instanceof DOMElement ? $child->attributes->length : 0);
                $stack[] = [$child, $depth + 1];
            }

            if ($nodes > $budget->maxNodes) {
                throw InvalidXml::because(\sprintf('A document has %d nodes at most.', $budget->maxNodes));
            }
        }
    }
}
