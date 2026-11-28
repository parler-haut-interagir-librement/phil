<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Agent;

use Phil\SitesConsoleBundle\Domain\Signature\VerifyingKey;

/**
 * A request the agent verified: its nonce, which the response signs back, and the console key that signed it.
 */
final readonly class VerifiedRequest
{
    public function __construct(
        public string $nonce,
        public VerifyingKey $signer,
    ) {
    }
}
