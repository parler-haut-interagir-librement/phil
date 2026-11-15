<?php

declare(strict_types=1);

namespace Phil\Lti\Key;

use Override;
use Phil\Lti\Contract\KeyStore;

/**
 * Keys held in memory: a current key and, published beside it, the public keys of earlier rotations. For tests, and
 * for an application that reads its keys from its configuration.
 */
final readonly class InMemoryKeyStore implements KeyStore
{
    /**
     * @param list<RsaPublicKey> $retired
     */
    public function __construct(
        private RsaPrivateKey $current,
        private array $retired = [],
    ) {
    }

    #[Override]
    public function signingKey(): RsaPrivateKey
    {
        return $this->current;
    }

    #[Override]
    public function publicKeys(): KeySet
    {
        return new KeySet([$this->current->publicKey, ...$this->retired]);
    }
}
