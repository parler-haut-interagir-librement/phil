<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Jwt;

use DateTimeImmutable;
use JsonException;
use Phil\OpenBadges\Contract\VerificationKey;
use Phil\OpenBadges\Encoding\Base64Url;
use Phil\OpenBadges\Exception\InvalidCredential;

/**
 * Verifies a credential secured as a JSON Web Token: its form, its algorithm — the key's, never one the token chooses
 * —, its signature, and its period of validity at the moment given.
 */
final readonly class JwtVerifier
{
    /**
     * The longest token read, in bytes.
     */
    public const int MAX_LENGTH = 1_048_576;

    public function __construct(
        private VerificationKey $key,
    ) {
    }

    /**
     * The claims of the token — the credential and its registered claims —, once verified.
     *
     * @return array<array-key, mixed>
     *
     * @throws InvalidCredential
     */
    public function verify(string $token, ?DateTimeImmutable $at = null): array
    {
        $parts = explode('.', $token);

        if (3 !== \count($parts) || \strlen($token) > self::MAX_LENGTH) {
            throw InvalidCredential::because('A credential token has three parts separated by dots.');
        }

        [$header, $payload, $signature] = $parts;

        if ((self::decode($header)['alg'] ?? null) !== $this->key->algorithm()) {
            throw InvalidCredential::because(\sprintf('The credential is not signed with %s.', $this->key->algorithm()));
        }

        if (!$this->key->verify($header.'.'.$payload, Base64Url::decode($signature))) {
            throw InvalidCredential::because('The signature of the credential does not verify.');
        }

        $claims = self::decode($payload);
        $now = $at?->getTimestamp();

        if (null !== $now && \is_int($claims['nbf'] ?? null) && $now < $claims['nbf']) {
            throw InvalidCredential::because('The credential is not valid yet.');
        }

        if (null !== $now && \is_int($claims['exp'] ?? null) && $now >= $claims['exp']) {
            throw InvalidCredential::because('The credential has expired.');
        }

        return $claims;
    }

    /**
     * The header and claims of a token, read without verifying it: to find which key verifies it.
     *
     * @return array{array<array-key, mixed>, array<array-key, mixed>}
     *
     * @throws InvalidCredential
     */
    public static function peek(string $token): array
    {
        $parts = explode('.', $token);

        if (3 !== \count($parts) || \strlen($token) > self::MAX_LENGTH) {
            throw InvalidCredential::because('A credential token has three parts separated by dots.');
        }

        return [self::decode($parts[0]), self::decode($parts[1])];
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function decode(string $part): array
    {
        try {
            $value = json_decode(Base64Url::decode($part), true, 64, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidCredential::because('A part of the credential token is not JSON.');
        }

        if (!\is_array($value)) {
            throw InvalidCredential::because('A part of the credential token is not a JSON object.');
        }

        return $value;
    }
}
