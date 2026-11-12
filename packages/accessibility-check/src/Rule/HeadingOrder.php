<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Rule;

use Override;
use Phil\AccessibilityCheck\Contract\HtmlRule;
use Phil\AccessibilityCheck\Contract\UjoRule;
use Phil\AccessibilityCheck\HeadingPolicy;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityCheck\Ujo\UjoTarget;
use Phil\AccessibilityCheck\Violation;

/**
 * `heading-order`: a heading goes at most one level deeper than the heading before it — an `h4` after an `h2` skips a
 * level, and a reader who moves from heading to heading loses the outline. Going back up any number of levels is
 * fine. When the policy forbids `h1`, the content sits under the title of its page, so its first heading is an `h2`.
 */
final class HeadingOrder implements HtmlRule, UjoRule
{
    #[Override]
    public function id(): string
    {
        return 'heading-order';
    }

    #[Override]
    public function criterion(): SuccessCriterion
    {
        return SuccessCriterion::InfoAndRelationships;
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
        return $this->check(Heading::ofHtml($target), $target->policy->headings);
    }

    /**
     * @return iterable<Violation>
     */
    #[Override]
    public function checkUjo(UjoTarget $target): iterable
    {
        return $this->check(Heading::ofUjo($target), $target->policy->headings);
    }

    /**
     * @param list<Heading> $headings
     *
     * @return list<Violation>
     */
    private function check(array $headings, HeadingPolicy $policy): array
    {
        $violations = [];
        $previous = HeadingPolicy::None === $policy ? 1 : null;

        foreach ($headings as $heading) {
            if (null !== $previous && $heading->level > $previous + 1) {
                $violations[] = new Violation($heading->location, new Message('heading-order.skipped', ['level' => $heading->level, 'previous' => $previous]));
            }

            $previous = $heading->level;
        }

        return $violations;
    }
}
