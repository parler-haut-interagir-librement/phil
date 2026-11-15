<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\WebAuthn;

use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;

/**
 * The public key of a passkey, as COSE writes it (RFC 9052, RFC 9053): an EC2 key on P-256 for ES256, an RSA key for
 * RS256, an OKP key on Ed25519 for EdDSA. Other types, curves and algorithms are refused.
 */
final class CoseKey
{
    private const int KTY = 1;

    private const int ALG = 3;

    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $map the decoded COSE map
     */
    public static function toPublicKey(array $map): PublicKey
    {
        $alg = $map[self::ALG] ?? null;
        $algorithm = \is_int($alg) ? SigningAlgorithm::fromCose($alg) : null;

        return match ([$map[self::KTY] ?? null, $algorithm]) {
            [2, SigningAlgorithm::ES256] => 1 === ($map[-1] ?? null)
                ? PublicKey::p256(self::bytes($map, -2), self::bytes($map, -3))
                : throw MalformedInput::because('An EC2 passkey is on P-256.'),
            [3, SigningAlgorithm::RS256] => PublicKey::rsa(self::bytes($map, -1), self::bytes($map, -2)),
            [1, SigningAlgorithm::EdDSA] => 6 === ($map[-1] ?? null)
                ? PublicKey::ed25519(self::bytes($map, -2))
                : throw MalformedInput::because('An OKP passkey is on Ed25519.'),
            default => throw MalformedInput::because('A passkey is an ES256, RS256 or EdDSA key.'),
        };
    }

    public static function decode(string $cose): PublicKey
    {
        $map = Cbor::decode($cose);

        if (!\is_array($map)) {
            throw MalformedInput::because('A COSE key is a map.');
        }

        return self::toPublicKey($map);
    }

    /**
     * @param array<array-key, mixed> $map
     */
    private static function bytes(array $map, int $label): string
    {
        $value = $map[$label] ?? null;

        return \is_string($value) && '' !== $value ? $value : throw MalformedInput::because(\sprintf('A COSE key has the member %d.', $label));
    }
}
