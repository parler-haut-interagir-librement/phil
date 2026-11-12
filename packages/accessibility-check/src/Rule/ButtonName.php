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
 * `button-name`: a button has a name — its text, the alternative of its image, its value for an `input`, a name of
 * ARIA or a `title`. A `button`, an `input` of type `button`, `submit` or `reset`, and any element of role `button`
 * are buttons; `submit` and `reset` inputs without a value take the name the browser gives them.
 */
final class ButtonName extends FragmentRule
{
    private const array BUTTON_INPUTS = ['button', 'submit', 'reset'];

    #[Override]
    public function id(): string
    {
        return 'button-name';
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
            $name = $element->localName;
            $isButton = 'button' === $name
                || ('input' === $name && \in_array(strtolower(trim($element->getAttribute('type') ?? '')), self::BUTTON_INPUTS, true))
                || $target->hasRole($element, 'button');

            if ($isButton && !$target->isHidden($element) && '' === $target->accessibleName($element)) {
                yield new Violation($target->locate($element), new Message('button-name.empty'));
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
