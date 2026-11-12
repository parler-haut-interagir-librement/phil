<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Application;

use Phil\DirectoryBundle\Domain\Model\Listing;

/**
 * A genre of the directory: its key, the path its pages are served under, and its title.
 */
final readonly class Genre
{
    public string $key;

    public string $path;

    public function __construct(
        string $key,
        string $path,
        public string $title,
    ) {
        $this->key = Listing::genreKey($key);
        $this->path = trim($path, '/');
    }
}
