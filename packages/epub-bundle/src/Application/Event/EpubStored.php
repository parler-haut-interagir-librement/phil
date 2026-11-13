<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Application\Event;

/**
 * Dispatched once a book written by a message is kept: the application learns where.
 */
final readonly class EpubStored
{
    /**
     * @param string $location where the storage keeps it: the path in a directory, the identifier of a medium
     */
    public function __construct(
        public string $source,
        public string $id,
        public string $path,
        public string $location,
    ) {
    }
}
