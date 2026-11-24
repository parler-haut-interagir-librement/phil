<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Rule;

use Override;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityCheck\Ujo\BlockVisit;
use Phil\AccessibilityCheck\Ujo\UjoTarget;
use Phil\AccessibilityCheck\Violation;

/**
 * `tab-name`: a tab has a name — its text, the alternative of an image in it, a name of ARIA or a `title`. Any element
 * of role `tab` is a tab; a screen reader announces a tab without a name as "tab, 2 of 3" only.
 */
final class TabName extends FragmentRule
{
    #[Override]
    public function id(): string
    {
        return 'tab-name';
    }

    #[Override]
    public function criterion(): SuccessCriterion
    {
        return SuccessCriterion::NameRoleValue;
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
        foreach ($target->elements() as $element) {
            if ($target->hasRole($element, 'tab') && !$target->isHidden($element) && '' === $target->accessibleName($element)) {
                yield new Violation($target->locate($element), new Message('tab-name.empty'));
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
