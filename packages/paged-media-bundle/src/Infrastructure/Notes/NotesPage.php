<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Infrastructure\Notes;

use Phil\FootnotesSidenotes\Resolution;
use Phil\PagedMediaBundle\Application\PrintableHtml;

/**
 * A page with its notes, and how they were resolved: the application reads the problems found and decides whether the
 * page is printed.
 */
final readonly class NotesPage
{
    public function __construct(
        public PrintableHtml $page,
        public Resolution $resolution,
    ) {
    }
}
