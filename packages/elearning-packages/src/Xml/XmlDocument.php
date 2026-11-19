<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Xml;

use DOMDocument;
use DOMElement;
use DOMNode;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageLimits;
use XMLReader;

/**
 * Reads a manifest as XML without trusting it: bounded in size, refused when it declares a document type — no
 * entity, internal or external, can then be expanded —, its nodes counted and its depth checked by a streaming pass
 * before a tree of it is built, parsed without network access, and walked by the local names of its elements, so that
 * a manifest with or without the usual namespaces reads the same.
 *
 * @internal the readers of the library read their manifests through it; it is not part of the public API
 */
final class XmlDocument
{
    public const int TEXT_LENGTH = 4096;

    private function __construct()
    {
    }

    /**
     * @throws PackageRefused when the document is too large, declares a document type, holds more nodes or nests its
     *                        elements deeper than the limits allow, or is not well formed
     */
    public static function root(string $xml, PackageLimits $limits, string $what): DOMElement
    {
        if (\strlen($xml) > $limits->manifestBytes) {
            throw PackageRefused::because(\sprintf('%s is larger than %d bytes.', $what, $limits->manifestBytes));
        }

        if (1 === preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw PackageRefused::because(\sprintf('%s declares a document type or an entity, which the reader refuses.', $what));
        }

        $previous = libxml_use_internal_errors(true);

        try {
            // Loading never throws on a text that is not empty: the errors it collects are cleared at once.
            $root = null;

            if ('' !== $xml) {
                self::measure($xml, $limits, $what);
                $document = new DOMDocument();
                $root = $document->loadXML($xml, \LIBXML_NONET) ? $document->documentElement : null;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$root instanceof DOMElement) {
            throw PackageRefused::because(\sprintf('%s is not well-formed XML.', $what));
        }

        return $root;
    }

    /**
     * Counts the nodes of a document and checks the depth of its elements as a stream, one node at a time, holding no
     * tree: a document is refused at the node past its budget, before anything of it is built. The pass stops at the
     * first error of a document that is not well formed, where the parser that builds the tree then stops too.
     *
     * @throws PackageRefused when the document holds more nodes or nests its elements deeper than the limits allow
     */
    private static function measure(string $xml, PackageLimits $limits, string $what): void
    {
        $reader = XMLReader::fromString($xml, null, \LIBXML_NONET);
        $nodes = 0;

        while ($reader->read()) {
            $type = $reader->nodeType;

            if (XMLReader::END_ELEMENT === $type) {
                continue;
            }

            if (XMLReader::ELEMENT === $type) {
                if ($reader->depth >= $limits->manifestDepth) {
                    throw PackageRefused::because(\sprintf('%s nests its elements deeper than %d levels.', $what, $limits->manifestDepth));
                }

                $nodes += $reader->attributeCount;
            }

            if (++$nodes > $limits->manifestNodes) {
                throw PackageRefused::because(\sprintf('%s holds more than %d XML nodes.', $what, $limits->manifestNodes));
            }
        }
    }

    /**
     * @return list<DOMElement> the child elements of that local name, in order
     */
    public static function children(DOMElement $parent, string $localName): array
    {
        $children = [];

        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === $localName) {
                $children[] = $node;
            }
        }

        return $children;
    }

    public static function child(DOMElement $parent, string $localName): ?DOMElement
    {
        return self::children($parent, $localName)[0] ?? null;
    }

    /**
     * The trimmed text of the first child of that local name, at most 4096 characters; null when there is none.
     */
    public static function childText(DOMElement $parent, string $localName): ?string
    {
        $child = self::child($parent, $localName);

        return $child instanceof DOMElement ? self::text($child) : null;
    }

    public static function text(DOMNode $node): string
    {
        return mb_substr(trim($node->textContent), 0, self::TEXT_LENGTH);
    }

    /**
     * The value of the attribute of that local name, whatever its namespace; null when there is none.
     */
    public static function attribute(DOMElement $element, string ...$localNames): ?string
    {
        foreach ($element->attributes as $attribute) {
            if (\in_array($attribute->localName, $localNames, true)) {
                return trim($attribute->value);
            }
        }

        return null;
    }

    /**
     * The element as XML, kept for information, or null when it is larger than the bound.
     */
    public static function markup(DOMElement $element, int $maxBytes): ?string
    {
        $markup = $element->ownerDocument?->saveXML($element);

        return !\is_string($markup) || \strlen($markup) > $maxBytes ? null : $markup;
    }
}
