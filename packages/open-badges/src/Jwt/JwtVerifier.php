<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Jwt;

use DateTimeImmutable;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use JsonException;
use Phil\OpenBadges\Contract\VerificationKey;
use Phil\OpenBadges\Encoding\Base64Url;
use Phil\OpenBadges\Exception\InvalidCredential;
use Throwable;

/**
 * Verifies a credential secured as a JSON Web Token: its form, its algorithm — the key's, never one the token chooses
 * —, its signature, and its period of validity at the moment given.
 *
 * The token is bounded before anything parses it — `MAX_LENGTH` bytes, three parts, a header and claims of `MAX_DEPTH`
 * levels at most, no critical header parameter —, then its signature is verified by `web-token/jwt-library` with the
 * key's algorithm only.
 */
final readonly class JwtVerifier
{
    /**
     * The longest token read, in bytes.
     */
    public const int MAX_LENGTH = 1_048_576;

    /**
     * The deepest nesting of the header and of the claims.
     */
    public const int MAX_DEPTH = 64;

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
        [$header, $claims] = self::peek($token);

        if (($header['alg'] ?? null) !== $this->key->algorithm()) {
            throw InvalidCredential::because(\sprintf('The credential is not signed with %s.', $this->key->algorithm()));
        }

        if (\array_key_exists('crit', $header) || \array_key_exists('b64', $header)) {
            throw InvalidCredential::because('A credential token marks no header parameter critical and encodes its claims.');
        }

        $algorithm = Algorithms::of($this->key->algorithm());

        try {
            $verified = new JWSVerifier(new AlgorithmManager([$algorithm]))->verifyWithKey(new CompactSerializer()->unserialize($token), $this->key->jwk(), 0);
        } catch (Throwable) {
            $verified = false;
        }

        if (!$verified) {
            throw InvalidCredential::because('The signature of the credential does not verify.');
        }

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
        $parts = \strlen($token) > self::MAX_LENGTH ? [] : explode('.', $token, 4);

        if (3 !== \count($parts)) {
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
            $value = json_decode(Base64Url::decode($part), true, self::MAX_DEPTH, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidCredential::because('A part of the credential token is not JSON.');
        }

        if (!\is_array($value)) {
            throw InvalidCredential::because('A part of the credential token is not a JSON object.');
        }

        return $value;
    }
}
