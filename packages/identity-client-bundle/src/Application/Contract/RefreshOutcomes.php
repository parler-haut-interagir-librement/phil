<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Contract;

use Phil\IdentityClientBundle\Application\Refresh\RefreshOutcome;
use SensitiveParameter;

/**
 * What became of a refresh token, shared between the requests of one session.
 *
 * Requests that read the session before a rotation still hold the previous refresh token: presenting it again would
 * make a rotating provider revoke the whole family, so they read the result of the rotation here.
 */
interface RefreshOutcomes
{
    public function find(#[SensitiveParameter] string $refreshToken): ?RefreshOutcome;

    public function publish(#[SensitiveParameter] string $refreshToken, RefreshOutcome $outcome): void;
}
