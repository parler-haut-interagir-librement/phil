<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer;

use Override;
use Phil\IpAnonymizer\Contract\SaltProvider;

/**
 * Always the same salt: for a salt the application rotates itself, by deployment or from its secrets, and for tests.
 * The digests of one address stay linkable for as long as the salt lives.
 */
final readonly class FixedSalt implements SaltProvider
{
    public function __construct(
        private Salt $salt,
    ) {
    }

    #[Override]
    public function current(): Salt
    {
        return $this->salt;
    }
}
