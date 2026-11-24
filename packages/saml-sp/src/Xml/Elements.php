<?php

declare(strict_types=1);

namespace Phil\SamlSp\Xml;

use DOMElement;

/**
 * Reads the direct children of an element by namespace and local name, never the descendants: what is read of a signed
 * element stays inside it, wherever the same names appear elsewhere in the document.
 */
final class Elements
{
    private function __construct()
    {
    }

    /**
     * @return list<DOMElement>
     */
    public static function all(DOMElement $parent, string $namespace, string $name): array
    {
        $found = [];

        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $namespace === $child->namespaceURI && $name === $child->localName) {
                $found[] = $child;
            }
        }

        return $found;
    }

    /**
     * The child when there is exactly one, null when there is none or more than one.
     */
    public static function one(DOMElement $parent, string $namespace, string $name): ?DOMElement
    {
        $found = self::all($parent, $namespace, $name);

        return 1 === \count($found) ? $found[0] : null;
    }

    /**
     * The value of an attribute without a namespace, null when it is absent.
     */
    public static function attribute(DOMElement $element, string $name): ?string
    {
        return $element->hasAttribute($name) ? $element->getAttribute($name) : null;
    }

    /**
     * Whether the element is the one of this namespace and local name.
     */
    public static function is(DOMElement $element, string $namespace, string $name): bool
    {
        return $namespace === $element->namespaceURI && $name === $element->localName;
    }

    /**
     * The text of the element with the spaces around it trimmed; every text node is read, comments left out.
     */
    public static function text(DOMElement $element): string
    {
        return trim($element->textContent);
    }

    /**
     * Every element of this namespace and local name in the document of the element, at any depth.
     */
    public static function countInDocument(DOMElement $element, string $namespace, string $name): int
    {
        return $element->ownerDocument?->getElementsByTagNameNS($namespace, $name)->length ?? 0;
    }
}
