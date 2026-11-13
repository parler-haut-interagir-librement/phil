<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application\Event;

use Phil\PagedMediaBundle\Application\PrintSubject;

/**
 * Dispatched before a print that names its work starts, or before it is queued: a listener that refuses the print
 * throws, and nothing is rendered, sent or kept.
 */
final readonly class PrintStarting
{
    public function __construct(
        public PrintSubject $subject,
    ) {
    }
}
