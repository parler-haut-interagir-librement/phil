<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Jose;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\EdDSA;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\Algorithm\SignatureAlgorithm;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use JsonException;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Exception\VerificationFailed;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Throwable;

/**
 * JSON Web Tokens signed in the compact serialization (RFC 7515, RFC 7519) by `web-token/jwt-library`: written with a
 * private key, read back with the public JSON Web Key its `kid` names.
 *
 * Before the library sees a token, the reader bounds it: at most MAX_LENGTH bytes, three parts, a header and claims
 * that are JSON objects of a bounded depth, a header that names an algorithm and a key, and no `crit` nor `b64`
 * member. The library then verifies the signature with the one algorithm of the key — `none` and HMAC are never
 * registered —, refusing a header that names another. The reader checks nothing of the claims: the expiry, the issuer
 * and the audience are the caller's.
 */
final class Jws
{
    public const int MAX_LENGTH = 8192;

    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $claims
     * @param array<string, mixed> $header more members of the header, such as `typ`
     */
    public static function encode(array $claims, PrivateKey $key, array $header = []): string
    {
        $jws = new JWSBuilder(self::algorithms($key->algorithm))
            ->create()
            ->withPayload(self::json($claims))
            ->addSignature($key->jwk(), ['alg' => $key->algorithm->value, 'kid' => $key->kid] + $header + ['typ' => 'JWT'])
            ->build();

        return new CompactSerializer()->serialize($jws, 0);
    }

    /**
     * The claims of a token whose signature one of the keys verifies.
     *
     * @param callable(string): ?JWK $keys the public key of a key identifier, null when unknown
     *
     * @return array<string, mixed>
     *
     * @throws MalformedInput     when the token is not a compact JSON Web Signature
     * @throws VerificationFailed when its key is unknown, of another algorithm, or its signature does not verify
     */
    public static function decode(string $token, callable $keys): array
    {
        if (\strlen($token) > self::MAX_LENGTH) {
            throw MalformedInput::because('The token is too long.');
        }

        $parts = explode('.', $token);

        if (3 !== \count($parts)) {
            throw MalformedInput::because('A compact JSON Web Signature has three parts.');
        }

        $header = self::object(Base64Url::decode($parts[0]));
        $claims = self::object(Base64Url::decode($parts[1]));
        $kid = $header['kid'] ?? null;

        if (\array_key_exists('crit', $header) || \array_key_exists('b64', $header) || !\is_string($header['alg'] ?? null) || !\is_string($kid)) {
            throw MalformedInput::because('The header names an algorithm and a key, and no critical extension.');
        }

        $key = $keys($kid);

        if (!$key instanceof JWK) {
            throw VerificationFailed::because('The token names a key the provider does not publish.');
        }

        $algorithm = SigningAlgorithm::tryFrom($header['alg']);

        if (!$algorithm instanceof SigningAlgorithm || !$key->has('alg') || $algorithm->value !== $key->get('alg') || !self::verifies($token, $key, $algorithm)) {
            throw VerificationFailed::because('The signature of the token does not verify.');
        }

        return $claims;
    }

    private static function verifies(string $token, JWK $key, SigningAlgorithm $algorithm): bool
    {
        try {
            return new JWSVerifier(self::algorithms($algorithm))->verifyWithKey(new CompactSerializer()->unserialize($token), $key, 0);
        } catch (Throwable) {
            return false;
        }
    }

    private static function algorithms(SigningAlgorithm $algorithm): AlgorithmManager
    {
        return new AlgorithmManager([self::algorithm($algorithm)]);
    }

    private static function algorithm(SigningAlgorithm $algorithm): SignatureAlgorithm
    {
        return match ($algorithm) {
            SigningAlgorithm::RS256 => new RS256(),
            SigningAlgorithm::ES256 => new ES256(),
            SigningAlgorithm::EdDSA => new EdDSA(),
        };
    }

    /**
     * @param array<string, mixed> $value
     */
    private static function json(array $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    private static function object(string $json): array
    {
        try {
            $value = json_decode($json, true, 16, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            throw MalformedInput::because('A part of the token is not JSON.');
        }

        if (!\is_array($value) || array_is_list($value) && [] !== $value) {
            throw MalformedInput::because('A part of the token is a JSON object.');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }
}
