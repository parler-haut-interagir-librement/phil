<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Contract;

use Phil\AccessibilityCheck\Ujo\UjoTarget;
use Phil\AccessibilityCheck\Violation;

/**
 * A rule that reads a UJO document: its blocks, their data and tunes, and the fields of their data that hold HTML.
 */
interface UjoRule extends Rule
{
    /**
     * What the rule finds in the document, in the order of its blocks.
     *
     * @return iterable<Violation>
     */
    public function checkUjo(UjoTarget $target): iterable;
}
