<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Jwt;

use DateTimeImmutable;
use Phil\OpenBadges\Contract\Signer;
use Phil\OpenBadges\Encoding\Base64Url;
use Phil\OpenBadges\Exception\InvalidBadge;

/**
 * Secures a credential as a JSON Web Token signed by the issuer's key — the VC-JWT securing that Open Badges 3.0 and
 * CLR 2.0 recognize —, verifiable away from the site with the issuer's public key.
 *
 * The token's header names the algorithm, the type `vc+jwt`, the key's identifier when one is given and the public key
 * as a JSON Web Key; its claims are the credential's, with `iss`, `jti`, `sub`, `nbf` and `exp` mirroring the issuer,
 * the identifier, the subject's identifier and the period of validity.
 */
final readonly class JwtSigner
{
    public function __construct(
        private Signer $signer,
        private ?string $keyId = null,
    ) {
    }

    /**
     * @param array<string, mixed> $credential an unsecured credential
     */
    public function sign(array $credential): string
    {
        if (\array_key_exists('proof', $credential)) {
            throw InvalidBadge::because('A credential secured as a JSON Web Token has no embedded proof.');
        }

        $header = array_filter(['alg' => $this->signer->algorithm(), 'typ' => 'vc+jwt', 'kid' => $this->keyId, 'jwk' => $this->signer->publicJwk()], static fn (array|string|null $value): bool => null !== $value);
        $signed = $this->json($header).'.'.$this->json([...$credential, ...$this->claims($credential)]);

        return $signed.'.'.Base64Url::encode($this->signer->sign($signed));
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
            'iss' => \is_array($issuer) ? $issuer['id'] ?? null : $issuer,
            'jti' => $credential['id'] ?? null,
            'sub' => \is_array($subject) ? $subject['id'] ?? null : null,
            'nbf' => $this->timestamp($credential['validFrom'] ?? null),
            'exp' => $this->timestamp($credential['validUntil'] ?? null),
        ], static fn (mixed $claim): bool => \is_int($claim) || \is_string($claim));
    }

    private function timestamp(mixed $moment): ?int
    {
        if (!\is_string($moment)) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat(\DATE_RFC3339, $moment);
        $parsed = false === $parsed ? DateTimeImmutable::createFromFormat(\DATE_RFC3339_EXTENDED, $moment) : $parsed;

        if (false === $parsed) {
            throw InvalidBadge::because(\sprintf('"%s" is not a moment of RFC 3339.', $moment));
        }

        return $parsed->getTimestamp();
    }

    /**
     * @param array<string, mixed> $value
     */
    private function json(array $value): string
    {
        return Base64Url::encode(json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
    }
}
