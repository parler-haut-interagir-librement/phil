<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Contract;

/**
 * Decides where a sign-in may land.
 */
interface ReturnToPolicy
{
    /**
     * Returns the target a browser may be sent to, or null to discard the candidate.
     */
    public function resolve(string $candidate): ?string;
}
