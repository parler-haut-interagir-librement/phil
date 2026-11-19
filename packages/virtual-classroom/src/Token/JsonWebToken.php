<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Token;

use DateTimeImmutable;
use JsonException;
use OpenSSLAsymmetricKey;
use Phil\VirtualClassroom\Exception\InvalidValue;
use Phil\VirtualClassroom\Exception\InvalidWebhook;
use SodiumException;

/**
 * The compact JSON Web Tokens the providers read and send: signed with HMAC-SHA-256, -384 or -512 under a shared
 * secret, or with RSA-SHA-256 under a private key. A token is checked against the algorithms its caller accepts —
 * never `none`, never the one its header asks for alone —, in constant time, then against its `exp` and `nbf` with a
 * minute of leeway.
 *
 * @internal
 */
final readonly class JsonWebToken
{
    public const int MAX_LENGTH = 16_384;

    private const array HMAC = ['HS256' => 'sha256', 'HS384' => 'sha384', 'HS512' => 'sha512'];

    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $claims
     * @param array<string, mixed> $header what the header holds besides `alg` and `typ`, a `kid` for instance
     *
     * @throws InvalidValue when the algorithm is not one the library signs with, or the key does not sign
     */
    public static function sign(array $claims, string $algorithm, string|OpenSSLAsymmetricKey $key, array $header = []): string
    {
        $input = self::encode(['alg' => $algorithm, 'typ' => 'JWT', ...$header]).'.'.self::encode($claims);

        return $input.'.'.self::base64url(self::signature($input, $algorithm, $key));
    }

    /**
     * The claims of a token signed under the secret with one of the algorithms accepted.
     *
     * @param non-empty-list<string> $algorithms among HS256, HS384 and HS512
     *
     * @return array<string, mixed>
     *
     * @throws InvalidWebhook when the token is malformed, signed otherwise, expired or not yet valid
     */
    public static function verify(string $token, string $secret, array $algorithms, DateTimeImmutable $now): array
    {
        $parts = explode('.', $token);

        if (\strlen($token) > self::MAX_LENGTH || 3 !== \count($parts)) {
            throw InvalidWebhook::because('The token is not a compact JSON Web Token.');
        }

        [$header, $claims, $signature] = $parts;
        $decodedHeader = self::decode($header);
        $algorithm = $decodedHeader['alg'] ?? null;

        if (!\is_string($algorithm) || !\in_array($algorithm, $algorithms, true) || !isset(self::HMAC[$algorithm])) {
            throw InvalidWebhook::because('The token is signed with an algorithm that is not accepted.');
        }

        $expected = self::base64url(hash_hmac(self::HMAC[$algorithm], $header.'.'.$claims, $secret, true));

        if (!hash_equals($expected, $signature)) {
            throw InvalidWebhook::because('The signature of the token does not match.');
        }

        $decoded = self::decode($claims);
        $time = $now->getTimestamp();

        if (isset($decoded['exp']) && (!\is_int($decoded['exp']) || $decoded['exp'] + 60 < $time)) {
            throw InvalidWebhook::because('The token has expired.');
        }

        if (isset($decoded['nbf']) && (!\is_int($decoded['nbf']) || $decoded['nbf'] - 60 > $time)) {
            throw InvalidWebhook::because('The token is not valid yet.');
        }

        return $decoded;
    }

    public static function base64url(string $bytes): string
    {
        return sodium_bin2base64($bytes, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /**
     * @throws InvalidValue when the algorithm is not one the library signs with, or the key does not sign
     */
    private static function signature(string $input, string $algorithm, string|OpenSSLAsymmetricKey $key): string
    {
        if (isset(self::HMAC[$algorithm]) && \is_string($key)) {
            return hash_hmac(self::HMAC[$algorithm], $input, $key, true);
        }

        if ('RS256' === $algorithm && $key instanceof OpenSSLAsymmetricKey && openssl_sign($input, $signature, $key, \OPENSSL_ALGO_SHA256) && \is_string($signature)) {
            return $signature;
        }

        throw InvalidValue::because(\sprintf('The token cannot be signed with "%s" and the key given.', $algorithm));
    }

    /**
     * @param array<string, mixed> $value
     */
    private static function encode(array $value): string
    {
        return self::base64url(json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidWebhook when the part is not base64url-encoded JSON of an object
     */
    private static function decode(string $part): array
    {
        try {
            $bytes = '' === $part ? '' : sodium_base642bin($part, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (SodiumException) {
            $bytes = false;
        }

        try {
            $value = false === $bytes ? null : json_decode($bytes, true, 16, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $value = null;
        }

        if (!\is_array($value) || array_is_list($value) && [] !== $value) {
            throw InvalidWebhook::because('A part of the token is not the JSON of an object.');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }
}
