<?php

declare(strict_types=1);

namespace Phil\Typography\Html;

use Closure;
use Dom\Attr;
use Dom\Element;
use Dom\HTMLDocument;

/**
 * The HTML of a tree, with `<` and `>` escaped in attribute values as the current HTML standard writes them: the
 * serializer of `Dom` leaves them as they are, and a tool that cuts HTML at its first `>` would then cut a tag inside
 * an attribute. Each bracket of a value is swapped for a mark the HTML does not hold, the tree written, and each mark
 * replaced by its character reference.
 *
 * @internal
 */
final readonly class Serializer
{
    /**
     * The HTML inside an element.
     */
    public static function inner(Element $element): string
    {
        return self::escaped($element, static fn (): string => $element->innerHTML);
    }

    /**
     * The HTML of a whole document.
     */
    public static function document(HTMLDocument $document): string
    {
        return self::escaped($document, static fn (): string => $document->saveHtml());
    }

    /**
     * @param Closure(): string $serialize
     */
    private static function escaped(Element|HTMLDocument $root, Closure $serialize): string
    {
        $attributes = [];

        foreach ($root->querySelectorAll('*') as $element) {
            foreach ($element->attributes as $attribute) {
                if (false !== strpbrk($attribute->value, '<>')) {
                    $attributes[] = $attribute;
                }
            }
        }

        if ([] === $attributes) {
            return $serialize();
        }

        $html = $serialize();
        $mark = "\u{E000}";

        while (str_contains($html, $mark)) {
            $mark .= "\u{E000}";
        }

        array_walk($attributes, static function (Attr $attribute) use ($mark): void {
            $attribute->value = str_replace(['<', '>'], [$mark.'l', $mark.'g'], $attribute->value);
        });

        return str_replace([$mark.'l', $mark.'g'], ['&lt;', '&gt;'], $serialize());
    }
}
