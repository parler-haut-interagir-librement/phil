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
 * `link-name`: a link has a name — its text, the alternative of its image, a name of ARIA or a `title`. An empty
 * link is announced by its address, or not at all. In UJO, a `button` block has a text.
 */
final class LinkName extends FragmentRule
{
    #[Override]
    public function id(): string
    {
        return 'link-name';
    }

    #[Override]
    public function criterion(): SuccessCriterion
    {
        return SuccessCriterion::LinkPurposeInContext;
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
        foreach ($target->elements('a') as $link) {
            $href = $link->getAttribute('href');

            if (null !== $href && !$target->isHidden($link) && '' === $target->accessibleName($link)) {
                yield new Violation($target->locate($link), new Message('link-name.empty', ['href' => $href]));
            }
        }
    }

    /**
     * @return iterable<Violation>
     */
    #[Override]
    protected function checkBlock(BlockVisit $visit, UjoTarget $target): iterable
    {
        if ('button' === $visit->block->type && '' === UjoTarget::text(UjoTarget::field($visit->block, 'text'))) {
            $link = UjoTarget::field($visit->block, 'link');

            yield new Violation($visit->locate('text'), new Message('link-name.empty', ['href' => \is_string($link) ? $link : '']));
        }
    }
}
