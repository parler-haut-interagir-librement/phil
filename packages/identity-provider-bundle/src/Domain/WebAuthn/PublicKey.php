<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\WebAuthn;

use Phil\IdentityProviderBundle\Domain\Encoding\Der;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;

/**
 * The public key of a passkey, read from COSE: an RSA key of at least 2048 bits, a P-256 key or an Ed25519 key, with
 * the algorithm it is used with. It verifies the signatures of assertions, ECDSA ones in DER as WebAuthn writes them.
 */
final readonly class PublicKey
{
    public const int MIN_RSA_BITS = 2048;

    /**
     * @param non-empty-string $material the PEM of an RSA or P-256 key, the 32 bytes of an Ed25519 key
     */
    private function __construct(
        public SigningAlgorithm $algorithm,
        private string $material,
    ) {
    }

    public static function rsa(string $modulus, string $exponent): self
    {
        $modulus = ltrim($modulus, "\x00");
        $exponent = ltrim($exponent, "\x00");

        if (8 * \strlen($modulus) < self::MIN_RSA_BITS || '' === $exponent || \strlen($exponent) > 8) {
            throw MalformedInput::because(\sprintf('An RSA key has a modulus of at least %d bits and an exponent of at most 64.', self::MIN_RSA_BITS));
        }

        return new self(SigningAlgorithm::RS256, Der::rsaPublicKey($modulus, $exponent));
    }

    public static function p256(string $x, string $y): self
    {
        $pem = Der::p256PublicKey($x, $y);

        if (false === openssl_pkey_get_public($pem)) {
            throw MalformedInput::because('The point is not on the P-256 curve.');
        }

        return new self(SigningAlgorithm::ES256, $pem);
    }

    public static function ed25519(string $x): self
    {
        if (\SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== \strlen($x)) {
            throw MalformedInput::because('An Ed25519 key holds 32 bytes.');
        }

        return new self(SigningAlgorithm::EdDSA, $x);
    }

    /**
     * Whether the signature over the input holds.
     */
    public function verify(string $input, string $signature): bool
    {
        if (SigningAlgorithm::EdDSA === $this->algorithm) {
            return \SODIUM_CRYPTO_SIGN_BYTES === \strlen($signature) && sodium_crypto_sign_verify_detached($signature, $input, $this->material);
        }

        return 1 === openssl_verify($input, $signature, $this->material, \OPENSSL_ALGO_SHA256);
    }
}
