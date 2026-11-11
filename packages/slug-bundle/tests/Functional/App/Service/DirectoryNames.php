<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Tests\Functional\App\Service;

use Phil\Slug\Slugger;

/**
 * A service of the application that asks for two sluggers: the default one, and one by its name.
 */
final readonly class DirectoryNames
{
    public function __construct(
        public Slugger $slugger,
        public Slugger $directorySlugger,
    ) {
    }
}
