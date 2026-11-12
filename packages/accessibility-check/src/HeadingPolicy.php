<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck;

/**
 * How many first-level headings a content may hold: a whole page has one, a content shown under the title of its
 * page has none.
 */
enum HeadingPolicy: string
{
    /** At most one `h1`: the content is the whole page, or holds its title. */
    case Single = 'single';

    /** No `h1`: the page shows its title above the content, whose headings start at `h2`. */
    case None = 'none';

    /** Any number of `h1`: the rule says nothing. */
    case Any = 'any';
}
