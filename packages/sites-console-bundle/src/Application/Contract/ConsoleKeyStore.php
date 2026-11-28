<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Contract;

use Phil\SitesConsoleBundle\Application\Exception\StateNotKept;

/**
 * Where an agent keeps the console keys a rotation gave it, as an opaque text written by ConsoleKeyRing. It holds
 * public keys only, but it decides which requests the site accepts: it must be shared by the site's servers, survive
 * deploys, and be writable by nobody but the application.
 */
interface ConsoleKeyStore
{
    public function read(): ?string;

    /**
     * @throws StateNotKept when the state could not be kept
     */
    public function write(string $state): void;
}
