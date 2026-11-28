<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Memory;

use Override;
use Phil\SitesConsoleBundle\Application\Contract\ConsoleKeyStore;

/**
 * The console keys a rotation gave the site, in memory, for tests and demonstrations.
 */
final class InMemoryConsoleKeyStore implements ConsoleKeyStore
{
    public function __construct(
        public ?string $state = null,
    ) {
    }

    #[Override]
    public function read(): ?string
    {
        return $this->state;
    }

    #[Override]
    public function write(string $state): void
    {
        $this->state = $state;
    }
}
