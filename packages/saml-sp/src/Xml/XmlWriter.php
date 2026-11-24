<?php

declare(strict_types=1);

namespace Phil\SamlSp\Xml;

use DOMDocument;
use DOMElement;
use LogicException;

/**
 * Builds the documents the service provider sends: elements with their namespace, their attributes and their text,
 * escaped by DOM.
 */
final class XmlWriter
{
    private function __construct()
    {
    }

    public static function document(string $namespace, string $qualifiedName): DOMElement
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = self::element($document, $namespace, $qualifiedName);
        $document->appendChild($root);

        return $root;
    }

    public static function element(DOMDocument $document, string $namespace, string $qualifiedName): DOMElement
    {
        $element = $document->createElementNS($namespace, $qualifiedName);

        if (!$element instanceof DOMElement) {
            throw new LogicException(\sprintf('The element "%s" cannot be created.', $qualifiedName));
        }

        return $element;
    }

    /**
     * @param array<string, string|null> $attributes the attributes without a namespace; a null value is left out
     */
    public static function child(DOMElement $parent, string $namespace, string $qualifiedName, array $attributes = [], ?string $text = null): DOMElement
    {
        $document = $parent->ownerDocument ?? new DOMDocument();
        $element = self::element($document, $namespace, $qualifiedName);

        foreach ($attributes as $name => $value) {
            if (null !== $value) {
                $element->setAttribute($name, $value);
            }
        }

        if (null !== $text) {
            $element->appendChild($document->createTextNode($text));
        }

        $parent->appendChild($element);

        return $element;
    }

    public static function xml(DOMElement $root): string
    {
        return (string) $root->ownerDocument?->saveXML($root);
    }
}
