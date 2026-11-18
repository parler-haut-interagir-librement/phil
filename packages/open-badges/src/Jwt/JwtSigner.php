<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Jwt;

use DateTimeImmutable;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Phil\OpenBadges\Contract\Signer;
use Phil\OpenBadges\Credentials;
use Phil\OpenBadges\Exception\InvalidBadge;
use Throwable;

/**
 * Secures a credential as a JSON Web Token signed by the issuer's key — the VC-JWT securing that Open Badges 3.0 and
 * CLR 2.0 recognize —, verifiable away from the site with the issuer's public key. The token is built, signed and
 * serialized by `web-token/jwt-library`, with the signer's algorithm and key.
 *
 * The token's header names the algorithm, the type `vc+jwt`, the key's identifier when one is given and the public key
 * as a JSON Web Key; its claims are the credential's, with `iss`, `jti`, `sub`, `nbf` and `exp` mirroring the issuer,
 * the identifier, the subject's identifier and the period of validity — strings, and seconds read from the moments of
 * RFC 3339 by the reader the verifier uses. A credential that carries one of those claims itself is refused.
 */
final readonly class JwtSigner
{
    public function __construct(
        private Signer $signer,
        private ?string $keyId = null,
    ) {
    }

    /**
     * The registered claims that mirror the credential's fields.
     */
    public const array CLAIMS = ['iss', 'jti', 'sub', 'nbf', 'exp'];

    /**
     * @param array<string, mixed> $credential an unsecured credential
     */
    public function sign(array $credential): string
    {
        if (\array_key_exists('proof', $credential)) {
            throw InvalidBadge::because('A credential secured as a JSON Web Token has no embedded proof.');
        }

        if ([] !== array_intersect_key($credential, array_flip(self::CLAIMS))) {
            throw InvalidBadge::because('A credential secured as a JSON Web Token carries no "iss", "jti", "sub", "nbf" or "exp" of its own: they mirror its fields.');
        }

        $algorithm = $this->signer->signatureAlgorithm();
        $header = array_filter(['alg' => $algorithm->name(), 'typ' => 'vc+jwt', 'kid' => $this->keyId, 'jwk' => $this->signer->publicJwk()], static fn (array|string|null $value): bool => null !== $value);
        $payload = json_encode([...$credential, ...$this->claims($credential)], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        try {
            $token = new JWSBuilder(new AlgorithmManager([$algorithm]))->create()->withPayload($payload)->addSignature($this->signer->jwk(), $header)->build();
        } catch (Throwable $throwable) {
            throw InvalidBadge::because(\sprintf('The signer cannot sign the token: %s', $throwable->getMessage()));
        }

        return new CompactSerializer()->serialize($token, 0);
    }

    /**
     * The registered claims a credential's own fields give.
     *
     * @param array<string, mixed> $credential
     *
     * @return array<string, int|string>
     */
    private function claims(array $credential): array
    {
        $issuer = $credential['issuer'] ?? null;
        $subject = $credential['credentialSubject'] ?? null;

        return array_filter([
            'iss' => $this->identifier(\is_array($issuer) ? $issuer['id'] ?? null : $issuer, 'The identifier of the issuer'),
            'jti' => $this->identifier($credential['id'] ?? null, 'The identifier of a credential'),
            'sub' => $this->identifier(\is_array($subject) ? $subject['id'] ?? null : null, 'The identifier of the subject'),
            'nbf' => $this->timestamp($credential['validFrom'] ?? null),
            'exp' => $this->timestamp($credential['validUntil'] ?? null),
        ], static fn (int|string|null $claim): bool => null !== $claim);
    }

    private function identifier(mixed $id, string $what): ?string
    {
        if (null !== $id && !\is_string($id)) {
            throw InvalidBadge::because(\sprintf('%s is a string.', $what));
        }

        return $id;
    }

    private function timestamp(mixed $moment): ?int
    {
        if (null === $moment) {
            return null;
        }

        $parsed = \is_string($moment) ? Credentials::parseMoment($moment) : null;

        if (!$parsed instanceof DateTimeImmutable) {
            throw InvalidBadge::because(\sprintf('%s is not a moment of RFC 3339.', \is_string($moment) ? '"'.$moment.'"' : get_debug_type($moment)));
        }

        return $parsed->getTimestamp();
    }
}
