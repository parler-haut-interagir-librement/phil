<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Application;

use Phil\Epub\Book;

/**
 * Builds a book of the application from an identifier: a story, a rulebook, a collection of articles. Registered under
 * a name with `#[AsBookSource]`, so that a message carries the name and the identifier, never the book.
 */
interface BookSource
{
    public function build(string $id): Book;
}
