<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Svg;

use Dom\Comment;
use Dom\Element;
use Dom\Node;
use Dom\ProcessingInstruction;
use Dom\Text;
use Dom\XMLDocument;
use DOMException;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use ValueError;

/**
 * Makes a purified SVG image smaller without changing its drawing: comments, processing instructions and `metadata`
 * dropped, the whitespace between elements removed — never inside `text`, `tspan`, `textPath`, `title`, `desc` or
 * `style`, nor under `xml:space="preserve"` —, and the coordinates of `d`, `points` and `transform` rounded to a number
 * of decimals.
 *
 * It reads the image as XML without network, as the purifier wrote it, and works in one pass over its nodes.
 */
final readonly class SvgMinifier
{
    /** The elements whose text is shown or read: their whitespace is kept. */
    private const array TEXT_ELEMENTS = ['text', 'tspan', 'textPath', 'title', 'desc', 'style'];

    /** The attributes whose numbers are coordinates, rounded without changing the drawing at the image's scale. */
    private const array ROUNDED_ATTRIBUTES = ['d', 'points', 'transform'];

    /**
     * @param int<0, 8> $precision the decimals kept in the coordinates
     */
    public function __construct(
        private int $precision = 3,
    ) {
    }

    /**
     * @throws InvalidMedia when the image is not well-formed XML
     */
    public function minify(string $svg): string
    {
        try {
            $document = XMLDocument::createFromString($svg, \LIBXML_NONET | \LIBXML_NOERROR | \LIBXML_NOWARNING);
        } catch (DOMException|ValueError $error) {
            throw InvalidMedia::because('The SVG image is not well-formed XML.', $error);
        }

        $root = $document->documentElement;

        if (!$root instanceof Element) {
            throw InvalidMedia::because('The SVG image has no root element.');
        }

        $this->clean($root, false);
        $minified = $document->saveXml($root);

        return false === $minified ? throw InvalidMedia::because('The SVG image cannot be written.') : $minified;
    }

    private function clean(Element $element, bool $preserve): void
    {
        $preserve = $preserve || 'preserve' === $element->getAttribute('xml:space') || \in_array($element->localName, self::TEXT_ELEMENTS, true);

        foreach (self::ROUNDED_ATTRIBUTES as $attribute) {
            $value = $element->getAttribute($attribute);

            if (null !== $value) {
                $element->setAttribute($attribute, $this->rounded($value));
            }
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($this->dropped($child, $preserve)) {
                $element->removeChild($child);
            } elseif ($child instanceof Element) {
                $this->clean($child, $preserve);
            }
        }
    }

    private function dropped(Node $node, bool $preserve): bool
    {
        return $node instanceof Comment
            || $node instanceof ProcessingInstruction
            || ($node instanceof Element && 'metadata' === $node->localName)
            || (!$preserve && $node instanceof Text && '' === trim($node->data));
    }

    /**
     * Each decimal number with more decimals than the precision, rounded. A number read without its leading zero, as
     * in `1.5.25`, is written without it; and one that comes out whole is kept apart from the number before or after
     * it by a space, so that `1.9999.5` stays two numbers.
     */
    private function rounded(string $value): string
    {
        return (string) preg_replace_callback('/\d*\.(\d+)(?![\deE])/', function (array $match) use ($value): string {
            [$number, $offset] = $match[0];

            if (\strlen($match[1][0]) <= $this->precision) {
                return $number;
            }

            $rounded = number_format(round((float) $number, $this->precision), $this->precision, '.', '');
            $rounded = str_contains($rounded, '.') ? rtrim(rtrim($rounded, '0'), '.') : $rounded;

            if ('.' === $number[0]) {
                $rounded = str_starts_with($rounded, '0.') ? substr($rounded, 1) : $rounded;
                $rounded = !str_starts_with($rounded, '.') && $offset > 0 && 1 === preg_match('/[\d.]/', $value[$offset - 1]) ? ' '.$rounded : $rounded;
            }

            return !str_contains($rounded, '.') && '.' === ($value[$offset + \strlen($number)] ?? '') ? $rounded.' ' : $rounded;
        }, $value, flags: \PREG_OFFSET_CAPTURE);
    }
}
