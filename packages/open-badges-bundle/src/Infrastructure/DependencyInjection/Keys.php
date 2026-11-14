<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Infrastructure\DependencyInjection;

use Phil\OpenBadges\Contract\Signer;
use Phil\OpenBadges\DataIntegrity\DidKeyMethods;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadges\Key\Ed25519Key;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Key\RsaSigner;
use SensitiveParameter;

/**
 * The factories of the services that hold keys: the issuer's signer from its secret, read when the service is first
 * used — the secret stays an environment variable until then —, the verification methods it knows, and its profile
 * document.
 *
 * @internal
 */
final class Keys
{
    private function __construct()
    {
    }

    public static function signer(string $algorithm, #[SensitiveParameter] string $key, #[SensitiveParameter] ?string $passphrase = null): Signer
    {
        return 'RS256' === $algorithm ? RsaSigner::fromPem($key, $passphrase) : Ed25519Signer::fromSeed($key);
    }

    /**
     * The issuer's own key under its verification method, and the keys the application trusts, given as Multikeys.
     *
     * @param array<string, string> $trusted the `publicKeyMultibase` of each trusted verification method
     */
    public static function methods(Signer $signer, string $verificationMethod, array $trusted = []): DidKeyMethods
    {
        $keys = array_map(Ed25519Key::fromMultibase(...), $trusted);

        return new DidKeyMethods([...$keys, $verificationMethod => $signer->verificationKey()]);
    }

    /**
     * The profile document published at the issuer's identifier, with its key as a Multikey when it is an Ed25519
     * key.
     *
     * @return array<string, mixed>
     */
    public static function profile(Issuer $issuer, Signer $signer, string $verificationMethod): array
    {
        $key = $signer->verificationKey();

        return $issuer->profileDocument($key instanceof Ed25519Key ? [$verificationMethod => $key] : []);
    }

    /**
     * The issuer's public keys as a JSON Web Key Set.
     *
     * @return array{keys: list<array<string, string>>}
     */
    public static function jwks(Signer $signer, string $verificationMethod): array
    {
        return ['keys' => [[...$signer->publicJwk(), 'kid' => $verificationMethod, 'alg' => $signer->algorithm(), 'use' => 'sig']]];
    }
}
