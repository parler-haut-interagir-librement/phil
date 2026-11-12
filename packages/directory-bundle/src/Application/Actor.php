<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Application;

/**
 * Who acts on the directory: the identifier of the user, null for nobody signed in, and whether they administer it.
 */
final readonly class Actor
{
    public function __construct(
        public ?string $user,
        public bool $admin = false,
    ) {
    }
}
