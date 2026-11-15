<?php

declare(strict_types=1);

namespace Phil\Lti\Jwt;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Phil\Lti\Key\RsaPrivateKey;

/**
 * Signs claims as a compact JSON Web Signature with `RS256` through `web-token/jwt-library`, `typ`, `alg` and the key
 * identifier in the protected header.
 */
final class JwtSigner
{
    /**
     * @param array<string, mixed> $claims
     */
    public static function sign(array $claims, RsaPrivateKey $key): string
    {
        $jws = new JWSBuilder(new AlgorithmManager([new RS256()]))
            ->create()
            ->withPayload(json_encode((object) $claims, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE))
            ->addSignature($key->jwk(), ['typ' => 'JWT', 'alg' => 'RS256', 'kid' => $key->keyId()])
            ->build();

        return new CompactSerializer()->serialize($jws, 0);
    }
}
