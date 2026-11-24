<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Contract;

use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Finding;

/**
 * One check of a published page. A rule reads the page and returns what it finds, in the order of the page; it never
 * changes the page, keeps nothing from one page to the next, and never throws for a page it finds wrong.
 */
interface SeoRule
{
    /**
     * The rule's name, in kebab case, the same for every finding: `canonical`, `image-alt`.
     */
    public function name(): string;

    /**
     * @return iterable<Finding>
     */
    public function check(CheckedPage $page): iterable;
}
