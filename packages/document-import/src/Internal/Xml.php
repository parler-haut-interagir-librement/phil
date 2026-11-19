<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal;

use Dom\Element;

/**
 * The few ways the readers walk the XML of a document: the children of an element in a namespace, the first of them
 * by name, the first descendant by name, and the metadata elements of a properties part.
 *
 * @internal
 */
final class Xml
{
    /**
     * The child elements in a namespace, of one name or of any.
     *
     * @return list<Element>
     */
    public static function children(?Element $parent, string $namespace, ?string $name = null): array
    {
        $children = [];

        foreach ($parent->children ?? [] as $child) {
            if ($namespace === $child->namespaceURI && (null === $name || $name === $child->localName)) {
                $children[] = $child;
            }
        }

        return $children;
    }

    public static function child(?Element $parent, string $namespace, string $name): ?Element
    {
        return self::children($parent, $namespace, $name)[0] ?? null;
    }

    public static function descendant(?Element $parent, string $namespace, string $name): ?Element
    {
        $found = $parent?->getElementsByTagNameNS($namespace, $name)->item(0);

        return $found instanceof Element ? $found : null;
    }

    /**
     * The text of the child elements of a properties part whose local names the map knows, under the names it gives;
     * empty texts are left out and repeated names joined by commas.
     *
     * @param array<string, string> $names
     *
     * @return array<string, string>
     */
    public static function metadata(?Element $parent, array $names): array
    {
        $metadata = [];

        foreach ($parent->children ?? [] as $child) {
            $name = $names[$child->localName] ?? null;
            $value = trim($child->textContent ?? '');

            if (null !== $name && '' !== $value) {
                $metadata[$name] = isset($metadata[$name]) ? $metadata[$name].', '.$value : $value;
            }
        }

        return $metadata;
    }
}
