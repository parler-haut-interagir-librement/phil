<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Application\Message;

/**
 * Writes the book a source builds from an identifier, in a worker, and keeps it under a path. It carries the source's
 * name and the identifier only: the book is built again in the worker, from the application's own records.
 */
final readonly class WriteEpub
{
    public function __construct(
        public string $source,
        public string $id,
        public string $path,
    ) {
    }
}
