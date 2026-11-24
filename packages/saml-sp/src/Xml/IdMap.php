<?php

declare(strict_types=1);

namespace Phil\SamlSp\Xml;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Phil\SamlSp\Exception\InvalidSignature;

/**
 * The elements of a document by their `ID` attribute, every `ID` unique: a reference resolves to one element, the one
 * the reader goes on to consume, never a copy planted beside it.
 */
final readonly class IdMap
{
    /**
     * @param array<string, DOMElement> $elements
     */
    private function __construct(private array $elements)
    {
    }

    public static function of(DOMDocument $document): self
    {
        $elements = [];
        $attributes = new DOMXPath($document)->query('//@ID');

        foreach (false === $attributes ? [] : $attributes as $attribute) {
            if (!$attribute instanceof DOMAttr || !$attribute->ownerElement instanceof DOMElement) {
                continue;
            }

            if (isset($elements[$attribute->value])) {
                throw InvalidSignature::because('Two elements of the document carry the same ID.');
            }

            $elements[$attribute->value] = $attribute->ownerElement;
        }

        return new self($elements);
    }

    public function element(string $id): ?DOMElement
    {
        return $this->elements[$id] ?? null;
    }
}
