<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Jwt;

use Jose\Component\Signature\Algorithm\EdDSA;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\Algorithm\SignatureAlgorithm;
use Phil\OpenBadges\Exception\InvalidCredential;

/**
 * The signature algorithms of `web-token/jwt-library` a credential is verified with, by JSON Web Algorithm name: the
 * verifier chooses it from the key, never from the token, and knows only `EdDSA` and `RS256`.
 *
 * @internal
 */
final class Algorithms
{
    /**
     * @throws InvalidCredential when the algorithm is neither `EdDSA` nor `RS256`
     */
    public static function of(string $name): SignatureAlgorithm
    {
        return match ($name) {
            'EdDSA' => new EdDSA(),
            'RS256' => new RS256(),
            default => throw InvalidCredential::because(\sprintf('The algorithm "%s" is not one a credential is verified with.', $name)),
        };
    }
}
