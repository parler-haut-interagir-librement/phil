<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application;

/**
 * The work a print is made of, by type and identifier, as the application names it: a book, a page, a set of rules.
 * Identifiers only: what the work holds stays with the application.
 */
final readonly class PrintSubject
{
    public function __construct(
        public string $type,
        public string $id,
    ) {
    }
}
