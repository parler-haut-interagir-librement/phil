<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Rule;

use Override;
use Phil\AccessibilityCheck\Contract\HtmlRule;
use Phil\AccessibilityCheck\Contract\UjoRule;
use Phil\AccessibilityCheck\Ujo\BlockVisit;
use Phil\AccessibilityCheck\Ujo\UjoTarget;
use Phil\AccessibilityCheck\Violation;

/**
 * A rule that reads HTML, and reads a UJO document block by block: first what the block's data says — an image
 * block's alternative text, an embed block's title —, then each of its fields that holds HTML, as HTML.
 */
abstract class FragmentRule implements HtmlRule, UjoRule
{
    /**
     * @return iterable<Violation>
     */
    #[Override]
    public function checkUjo(UjoTarget $target): iterable
    {
        foreach ($target->blocks() as $visit) {
            yield from $this->checkBlock($visit, $target);

            foreach ($target->fragments($visit) as $fragment) {
                yield from $this->checkHtml($fragment->html);
            }
        }
    }

    /**
     * What the rule finds in the data of one block, its HTML fields aside.
     *
     * @return iterable<Violation>
     */
    abstract protected function checkBlock(BlockVisit $visit, UjoTarget $target): iterable;
}
