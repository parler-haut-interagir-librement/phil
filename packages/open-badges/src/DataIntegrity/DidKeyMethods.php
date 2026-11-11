<?php

declare(strict_types=1);

namespace Phil\OpenBadges\DataIntegrity;

use Override;
use Phil\OpenBadges\Contract\VerificationKey;
use Phil\OpenBadges\Contract\VerificationMethods;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Key\Ed25519Key;

/**
 * Verification methods by identifier: the keys the application knows — its own issuer's, its partners' —, and every
 * Ed25519 `did:key`, whose key is its identifier. Nothing is fetched.
 */
final readonly class DidKeyMethods implements VerificationMethods
{
    /**
     * @param array<string, VerificationKey> $known the keys by verification method identifier
     */
    public function __construct(
        private array $known = [],
    ) {
    }

    #[Override]
    public function key(string $verificationMethod): VerificationKey
    {
        if (isset($this->known[$verificationMethod])) {
            return $this->known[$verificationMethod];
        }

        if (1 === preg_match('/^did:key:(z6Mk[1-9A-HJ-NP-Za-km-z]+)(?:#(z6Mk[1-9A-HJ-NP-Za-km-z]+))?$/D', $verificationMethod, $match) && (!isset($match[2]) || $match[1] === $match[2])) {
            return Ed25519Key::fromMultibase($match[1]);
        }

        throw InvalidCredential::because(\sprintf('The verification method "%s" is not one the verifier knows.', $verificationMethod));
    }
}
