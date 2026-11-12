<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Rule;

use Override;
use Phil\AccessibilityCheck\Contract\HtmlRule;
use Phil\AccessibilityCheck\Contract\UjoRule;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityCheck\Ujo\UjoTarget;
use Phil\AccessibilityCheck\Violation;

/**
 * `empty-heading`: a heading has text. An empty heading is announced as a heading with nothing to say, and breaks the
 * outline a reader moves through.
 */
final class EmptyHeading implements HtmlRule, UjoRule
{
    #[Override]
    public function id(): string
    {
        return 'empty-heading';
    }

    #[Override]
    public function criterion(): SuccessCriterion
    {
        return SuccessCriterion::HeadingsAndLabels;
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
        return $this->check(Heading::ofHtml($target));
    }

    /**
     * @return iterable<Violation>
     */
    #[Override]
    public function checkUjo(UjoTarget $target): iterable
    {
        return $this->check(Heading::ofUjo($target));
    }

    /**
     * @param list<Heading> $headings
     *
     * @return list<Violation>
     */
    private function check(array $headings): array
    {
        $violations = [];

        foreach ($headings as $heading) {
            if ('' === $heading->text) {
                $violations[] = new Violation($heading->location, new Message('empty-heading.empty', ['level' => $heading->level]));
            }
        }

        return $violations;
    }
}
