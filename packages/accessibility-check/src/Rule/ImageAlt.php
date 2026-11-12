<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Rule;

use Dom\Element;
use Override;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Location;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityCheck\Ujo\BlockVisit;
use Phil\AccessibilityCheck\Ujo\UjoTarget;
use Phil\AccessibilityCheck\Violation;

/**
 * `image-alt`: an image says what it shows. In HTML, an `img`, an `area` that links and an `input type="image"` have
 * an `alt` — or a name of ARIA or a `title`; `alt=""` marks a decorative image and is accepted, as is an image of role
 * `presentation` or `none`. In UJO, an `image` block has an alternative text or a caption, and each file of a
 * `gallery` a caption. An alternative of spaces only, or that is a file name, is a warning.
 */
final class ImageAlt extends FragmentRule
{
    #[Override]
    public function id(): string
    {
        return 'image-alt';
    }

    #[Override]
    public function criterion(): SuccessCriterion
    {
        return SuccessCriterion::NonTextContent;
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
        foreach ($target->elements('img', 'area', 'input') as $element) {
            if ($target->isHidden($element) || !$this->needsAlternative($target, $element)) {
                continue;
            }

            $source = $element->getAttribute('src') ?? $element->getAttribute('href') ?? '';
            $alt = $element->getAttribute('alt');
            $location = $target->locate($element);

            if (null === $alt) {
                if ('' === $target->labelOf($element) && '' === HtmlTarget::collapse($element->getAttribute('title') ?? '')) {
                    yield new Violation($location, new Message('image-alt.missing', ['source' => $source]));
                }
            } else {
                yield from $this->checkAlternative($alt, $source, $location, true);
            }
        }
    }

    /**
     * @return iterable<Violation>
     */
    #[Override]
    protected function checkBlock(BlockVisit $visit, UjoTarget $target): iterable
    {
        $block = $visit->block;

        if ('image' === $block->type) {
            $alt = UjoTarget::field($block, 'alt');
            $source = UjoTarget::field($block, 'file', 'url');
            $text = \is_string($alt) && '' !== trim($alt) ? $alt : UjoTarget::text(UjoTarget::field($block, 'caption'));

            yield from $this->checkAlternative($text, \is_string($source) ? $source : '', $visit->locate(), false);
        }

        if ('gallery' === $block->type) {
            $files = UjoTarget::field($block, 'files');

            foreach (\is_array($files) ? $files : [] as $index => $file) {
                $source = UjoTarget::field($block, 'files', $index, 'url');
                $text = UjoTarget::text(UjoTarget::field($block, 'files', $index, 'caption'));

                yield from $this->checkAlternative($text, \is_string($source) ? $source : '', $visit->locate('files['.$index.']'), false);
            }
        }
    }

    private function needsAlternative(HtmlTarget $target, Element $element): bool
    {
        return match ($element->localName) {
            'img' => !$target->hasRole($element, 'presentation', 'none'),
            'area' => $element->hasAttribute('href'),
            default => 'image' === strtolower(trim($element->getAttribute('type') ?? '')),
        };
    }

    /**
     * @param bool $decorativeAllowed whether `''` marks a decorative image, as `alt=""` does in HTML
     *
     * @return iterable<Violation>
     */
    private function checkAlternative(string $alternative, string $source, Location $location, bool $decorativeAllowed): iterable
    {
        if ('' === $alternative && $decorativeAllowed) {
            return;
        }

        if ('' === trim($alternative)) {
            yield '' === $alternative
                ? new Violation($location, new Message('image-alt.missing', ['source' => $source]))
                : new Violation($location, new Message('image-alt.blank', ['source' => $source]), Severity::Warning);
        } elseif (FileName::looksLikeOne($alternative, $source)) {
            yield new Violation($location, new Message('image-alt.filename', ['source' => $source, 'alt' => trim($alternative)]), Severity::Warning);
        }
    }
}
