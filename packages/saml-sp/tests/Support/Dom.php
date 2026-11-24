<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use RuntimeException;

/**
 * Typed DOM for the tests: elements created, appended and documents parsed without the `false` of the DOM stubs.
 */
final class Dom
{
    public static function create(DOMNode $context, string $namespace, string $name, ?string $text = null): DOMElement
    {
        $document = $context instanceof DOMDocument ? $context : $context->ownerDocument;
        $element = $document?->createElementNS($namespace, $name);

        if (!$element instanceof DOMElement) {
            throw new RuntimeException(\sprintf('The element "%s" cannot be created.', $name));
        }

        if (null !== $text) {
            $element->appendChild(new DOMText($text));
        }

        return $element;
    }

    public static function append(DOMElement $parent, string $namespace, string $name, ?string $text = null): DOMElement
    {
        $element = self::create($parent, $namespace, $name, $text);
        $parent->appendChild($element);

        return $element;
    }

    public static function load(string $xml): DOMDocument
    {
        $document = new DOMDocument();

        if ('' === $xml || !$document->loadXML($xml)) {
            throw new RuntimeException('The XML is not well-formed.');
        }

        return $document;
    }
}
