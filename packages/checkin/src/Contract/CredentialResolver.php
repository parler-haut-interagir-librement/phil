<?php

declare(strict_types=1);

namespace Phil\Checkin\Contract;

use Phil\Checkin\Credential;
use Phil\Checkin\CredentialCode;

/**
 * Finds the credential a code stands for: the application's tickets, registrations or cards, or a code that carries
 * its credential signed inside it. A resolver answers null for a code it does not know, so that the next one is asked.
 */
interface CredentialResolver
{
    public function resolve(CredentialCode $code): ?Credential;
}
