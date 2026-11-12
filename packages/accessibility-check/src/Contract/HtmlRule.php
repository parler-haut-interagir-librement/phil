<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Contract;

use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Violation;

/**
 * A rule that reads HTML: a whole page, a fragment of one, or a field of a UJO block that holds HTML.
 */
interface HtmlRule extends Rule
{
    /**
     * What the rule finds in the content, in document order.
     *
     * @return iterable<Violation>
     */
    public function checkHtml(HtmlTarget $target): iterable;
}
