<?php

declare(strict_types=1);

namespace Phil\Markup;

/**
 * Keeps of some HTML what a level allows, and removes everything that could run in a reader's browser or reach
 * outside the page: scripts, event handlers, `javascript:` addresses, frames, forms, styles.
 */
interface HtmlPurifier
{
    public function purify(string $html, Level $level): string;
}
