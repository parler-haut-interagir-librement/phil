<?php

declare(strict_types=1);

namespace Phil\Lti\Contract;

use Phil\Lti\Key\KeySet;
use Phil\Lti\Key\RsaPrivateKey;

/**
 * The keys of this side: the current one, which signs, and the key set it publishes — the current key with the keys
 * of the earlier rotations still in their overlap, so that a token signed just before a rotation verifies.
 */
interface KeyStore
{
    public function signingKey(): RsaPrivateKey;

    public function publicKeys(): KeySet;
}
