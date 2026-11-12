<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Rule;

use Dom\Element;
use Override;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityCheck\Ujo\BlockVisit;
use Phil\AccessibilityCheck\Ujo\UjoTarget;
use Phil\AccessibilityCheck\Violation;

/**
 * `svg-name`: a drawing written in the page says what it shows — a `title` child, or a name of ARIA —, or is marked
 * decorative with `aria-hidden="true"` or a role of `presentation` or `none`. A drawing nested in another, or inside a
 * link or a button, which names it, is left alone. In UJO, the drawings a block keeps in `rendered` are checked too.
 */
final class SvgName extends FragmentRule
{
    #[Override]
    public function id(): string
    {
        return 'svg-name';
    }

    #[Override]
    public function criterion(): SuccessCriterion
    {
        return SuccessCriterion::NonTextContent;
    }

    #[Override]
    public function severity(): Severity
    {
        return Severity::Warning;
    }

    /**
     * @return iterable<Violation>
     */
    #[Override]
    public function checkHtml(HtmlTarget $target): iterable
    {
        foreach ($target->elements('svg') as $svg) {
            if ($target->isHidden($svg) || $target->hasRole($svg, 'presentation', 'none') || null !== $svg->closest('svg svg, a svg, button svg')) {
                continue;
            }

            $title = $target->svgTitle($svg);

            if ('' === $target->labelOf($svg) && (!$title instanceof Element || '' === $target->textOf($title))) {
                yield new Violation($target->locate($svg), new Message('svg-name.missing'));
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
}
