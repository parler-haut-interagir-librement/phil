<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Rule;

use Dom\Element;
use Override;
use Phil\AccessibilityCheck\Colour\Colour;
use Phil\AccessibilityCheck\Colour\ColourParser;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityCheck\Ujo\BlockVisit;
use Phil\AccessibilityCheck\Ujo\UjoTarget;
use Phil\AccessibilityCheck\Violation;
use WeakMap;

/**
 * `color-contrast`: a text the content colours stands out from its background, by the contrast ratio of WCAG — 4.5:1,
 * or 3:1 for large text, at level AA; 7:1 and 4.5:1 at level AAA.
 *
 * Only the colours the content gives are known: a `style` of `color`, `background-color` or a `background` of one
 * colour, the `color` and `bgcolor` attributes, and the tokens of the look in `data-color`, as UJO writes them, whose
 * colours the policy holds. A text the content gives no colour to is the look's business and is not checked; where
 * the content gives only one of the two colours, the policy's foreground or background stands for the other.
 * Translucent colours are laid over what is behind them. A colour may be written in hexadecimal, `rgb()`, `hsl()`,
 * `oklch()` or by name. A text over an image or a gradient, or in a colour that cannot be computed, is left alone; one
 * coloured with `var()` or `color-mix()`, which only the style sheets of the page resolve, is reported once as a notice,
 * `color-contrast.unresolved`, on the element that gives that colour, so that a person checks it in a browser.
 *
 * Large text is 24 CSS pixels, or 18.66 in bold: the size is read from an inline `font-size` in `px`, `pt`, `em`,
 * `rem` or `%` of 16 pixels, else from the default size of the heading the text is in; the weight from an inline
 * `font-weight`, else from a `b`, `strong`, `th` or heading around it.
 */
final class ColorContrast extends FragmentRule
{
    private const array HEADING_SIZES = ['h1' => 32.0, 'h2' => 24.0, 'h3' => 18.72, 'h4' => 16.0, 'h5' => 13.28, 'h6' => 10.72];

    private const array BOLD_ELEMENTS = ['b', 'strong', 'th', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    private const string LENGTH = '/^(?<size>\d*\.?\d+)(?<unit>px|pt|r?em|%)$/D';

    #[Override]
    public function id(): string
    {
        return 'color-contrast';
    }

    #[Override]
    public function criterion(): SuccessCriterion
    {
        return SuccessCriterion::ContrastMinimum;
    }

    #[Override]
    public function severity(): Severity
    {
        return Severity::Error;
    }

    /**
     * @return iterable<Violation>
     */
    #[Override]
    public function checkHtml(HtmlTarget $target): iterable
    {
        $policy = $target->policy;
        /** @var WeakMap<Element, array{Colour|false|null, list<Colour>|false}> $colours */
        $colours = new WeakMap();

        foreach ($target->elements() as $element) {
            [$inheritedForeground, $inheritedLayers] = $element->parentElement instanceof Element ? $colours[$element->parentElement] : [null, []];
            $foreground = $this->ownForeground($target, $element) ?? $inheritedForeground;
            $layers = $this->layers($target, $element, $inheritedLayers);
            $colours[$element] = [$foreground, $layers];
            $unresolved = $this->unresolved($target, $element);

            if (null !== $unresolved && '' !== trim($element->textContent ?? '') && !$target->isHidden($element)) {
                yield new Violation(
                    $target->locate($element),
                    new Message('color-contrast.unresolved', ['value' => mb_strimwidth($unresolved, 0, 60, '…')]),
                    Severity::Notice,
                );
            }

            if (false === $foreground || false === $layers || (null === $foreground && [] === $layers) || $target->isHidden($element)) {
                continue;
            }

            $text = $target->ownText($element);

            if ('' === $text) {
                continue;
            }

            $background = $policy->background;

            foreach ($layers as $layer) {
                $background = $layer->over($background);
            }

            $foreground = ($foreground ?? $policy->foreground)->over($background);
            $ratio = $foreground->contrastWith($background);
            $required = $policy->contrast->minimumRatio($this->isLarge($target, $element));

            if ($ratio < $required) {
                yield new Violation(
                    $target->locate($element),
                    new Message('color-contrast.insufficient', [
                        'text' => mb_strimwidth($text, 0, 40, '…'),
                        'ratio' => \sprintf('%.2f', floor($ratio * 100) / 100),
                        'foreground' => $foreground->toHex(),
                        'background' => $background->toHex(),
                        'required' => $required,
                    ]),
                    null,
                    $policy->contrast->criterion(),
                );
            }
        }
    }

    /**
     * @return iterable<Violation>
     */
    #[Override]
    protected function checkBlock(BlockVisit $visit, UjoTarget $target): iterable
    {
        return [];
    }

    /**
     * The colour the element gives with `var()` or `color-mix()`, its text or its background, when it gives one.
     */
    private function unresolved(HtmlTarget $target, Element $element): ?string
    {
        $style = $target->style($element);

        foreach ([$style['color'] ?? null, $style['background-color'] ?? null, $style['background'] ?? null] as $value) {
            if (null !== $value && ColourParser::isUnresolved($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * The colour the element gives its text: a token of the look, a `color` in its style or its attribute; `null` when
     * it gives none or inherits, `false` when it cannot be computed.
     */
    private function ownForeground(HtmlTarget $target, Element $element): Colour|false|null
    {
        $token = $element->getAttribute('data-color');

        if (null !== $token && isset($target->policy->colourTokens[$token])) {
            return $target->policy->colourTokens[$token];
        }

        $value = $target->style($element)['color'] ?? $element->getAttribute('color');

        return null === $value || ColourParser::isInherited($value) ? null : ColourParser::parse($value) ?? false;
    }

    /**
     * The colours behind the element's text, the outermost first, from the last opaque one: the element's own laid over
     * those of its parent; `false` when an image, a gradient or a colour that cannot be computed is among them.
     *
     * @param list<Colour>|false $inherited the layers of the parent
     *
     * @return list<Colour>|false
     */
    private function layers(HtmlTarget $target, Element $element, array|false $inherited): array|false
    {
        $style = $target->style($element);
        $shorthand = $style['background'] ?? null;
        $value = $style['background-color'] ?? $shorthand ?? $element->getAttribute('bgcolor');
        $layer = null === $value || ColourParser::isInherited($value) ? null : ColourParser::parse($value) ?? false;

        if (false === $layer || 'none' !== ($style['background-image'] ?? 'none') || (null !== $shorthand && 1 === preg_match('/url\(|gradient\(/', $shorthand))) {
            return false;
        }

        if (!$layer instanceof Colour) {
            return $inherited;
        }

        if ($layer->isOpaque()) {
            return [$layer];
        }

        return false === $inherited ? false : [...$inherited, $layer];
    }

    private function isLarge(HtmlTarget $target, Element $element): bool
    {
        $size = $this->size($target, $element);

        return null !== $size && ($size >= 24.0 || ($size >= 18.66 && $this->isBold($target, $element)));
    }

    /**
     * The size of the text in CSS pixels; `null` when neither the content nor a heading gives it, which is the normal
     * size of the look.
     */
    private function size(HtmlTarget $target, Element $element): ?float
    {
        for ($node = $element; $node instanceof Element; $node = $node->parentElement) {
            $value = $target->style($node)['font-size'] ?? null;

            if (null !== $value && 1 === preg_match(self::LENGTH, $value, $match)) {
                return (float) $match['size'] * match ($match['unit']) {
                    'px' => 1.0,
                    'pt' => 4 / 3,
                    '%' => 0.16,
                    default => 16.0,
                };
            }
        }

        for ($node = $element; $node instanceof Element; $node = $node->parentElement) {
            $heading = self::HEADING_SIZES[$node->localName] ?? null;

            if (null !== $heading) {
                return $heading;
            }
        }

        return null;
    }

    private function isBold(HtmlTarget $target, Element $element): bool
    {
        for ($node = $element; $node instanceof Element; $node = $node->parentElement) {
            $weight = $target->style($node)['font-weight'] ?? null;

            if (null !== $weight) {
                return \in_array($weight, ['bold', 'bolder'], true) || (is_numeric($weight) && $weight >= 700);
            }

            if (\in_array($node->localName, self::BOLD_ELEMENTS, true)) {
                return true;
            }
        }

        return false;
    }
}
