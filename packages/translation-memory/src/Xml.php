<?php

declare(strict_types=1);

namespace Phil\TranslationMemory;

use DOMDocument;
use DOMDocumentType;
use DOMElement;

/**
 * The XML of an exchange file read safely: a document type declaration refused before parsing, nothing fetched from
 * the network, no entity expanded, and the elements a reader walks.
 *
 * @internal
 */
final class Xml
{
    public const string XML_NAMESPACE = 'http://www.w3.org/XML/1998/namespace';

    private function __construct()
    {
    }

    /**
     * The root element of a document; `null` when the text declares a document type or is not well-formed XML.
     */
    public static function root(string $xml): ?DOMElement
    {
        if ('' === $xml || false !== stripos($xml, '<!DOCTYPE')) {
            return null;
        }

        $document = new DOMDocument();
        $errors = libxml_use_internal_errors(true);
        $document->loadXML($xml, \LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($errors);

        // Checked again on the tree: a document in UTF-16 or UTF-32 hides its declaration from the search above.
        return $document->doctype instanceof DOMDocumentType ? null : $document->documentElement;
    }

    /**
     * The child elements of an element with one of the local names, in order.
     *
     * @return list<DOMElement>
     */
    public static function children(DOMElement $parent, string ...$names): array
    {
        $children = [];

        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && \in_array($child->localName, $names, true)) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * The language of an element: its `xml:lang`, or its `lang` as older formats write it.
     */
    public static function language(DOMElement $element): string
    {
        $language = $element->getAttributeNS(self::XML_NAMESPACE, 'lang');

        return '' === $language ? $element->getAttribute('lang') : $language;
    }
}
