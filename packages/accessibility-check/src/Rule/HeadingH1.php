<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Rule;

use Override;
use Phil\AccessibilityCheck\Contract\HtmlRule;
use Phil\AccessibilityCheck\Contract\UjoRule;
use Phil\AccessibilityCheck\HeadingPolicy;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Location;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityCheck\Ujo\UjoTarget;
use Phil\AccessibilityCheck\Violation;

/**
 * `heading-h1`: the first-level headings the policy allows. `Single`: one at most, each further `h1` reported, and a
 * whole HTML page without any noted; `None`: none, since the page shows its title above the content; `Any`: nothing
 * is checked.
 */
final class HeadingH1 implements HtmlRule, UjoRule
{
    #[Override]
    public function id(): string
    {
        return 'heading-h1';
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
        $headings = Heading::ofHtml($target);
        $violations = $this->check($headings, $target->policy->headings);

        if ($target->isFullDocument && HeadingPolicy::Single === $target->policy->headings && [] === $this->firstLevel($headings)) {
            $violations[] = new Violation(new Location(), new Message('heading-h1.missing'), Severity::Notice);
        }

        return $violations;
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

        foreach ($this->firstLevel($headings) as $position => $heading) {
            if (HeadingPolicy::None === $policy) {
                $violations[] = new Violation($heading->location, new Message('heading-h1.forbidden'));
            } elseif (HeadingPolicy::Single === $policy && $position > 0) {
                $violations[] = new Violation($heading->location, new Message('heading-h1.multiple', ['position' => $position + 1]));
            }
        }

        return $violations;
    }

    /**
     * @param list<Heading> $headings
     *
     * @return list<Heading>
     */
    private function firstLevel(array $headings): array
    {
        return array_values(array_filter($headings, static fn (Heading $heading): bool => 1 === $heading->level));
    }
}
