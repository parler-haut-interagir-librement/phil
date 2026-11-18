<?php

declare(strict_types=1);

namespace Phil\Markup;

/**
 * Keeps of some HTML what a level allows, and removes everything that could run in a reader's browser or reach
 * outside the page: scripts, event handlers, `javascript:` addresses, frames, forms, styles.
 *
 * The purifiers of the library refuse an HTML longer than their `maxBytes` whole, by `Exception\InputTooLarge`, before
 * they purify it: it is never cut to fit. A purifier of an application may have no ceiling, so the interface does not
 * declare the exception.
 */
interface HtmlPurifier
{
    /** The ceiling of an HTML, in bytes, of each purifier of the library unless configured: 4 MiB. */
    public const int DEFAULT_MAX_BYTES = 4_194_304;

    public function purify(string $html, Level $level): string;
}
