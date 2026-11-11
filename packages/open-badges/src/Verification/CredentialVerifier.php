<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Verification;

use DateTimeImmutable;
use JsonException;
use Phil\OpenBadges\Baking\Baked;
use Phil\OpenBadges\Contract\VerificationMethods;
use Phil\OpenBadges\DataIntegrity\EddsaRdfc2022;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Jwt\JwtVerifier;

/**
 * Verifies a credential however it is secured: a compact JSON Web Token, through the key its `kid` names — or the
 * default method when it names none —, or the JSON of a document with an `eddsa-rdfc-2022` proof, through its
 * verification method. Then that the key is one of the credential's issuer, and, at the moment given — the current
 * time when none is —, that the credential is valid already and not expired.
 *
 * The keys come from the verification methods the application gives: nothing is fetched, and a key a token carries in
 * its own header is never trusted. A verification method is one of an issuer when it is the issuer's identifier, a
 * fragment of it — `https://example.org/issuer#key-1`, `did:key:z6Mk…#z6Mk…` —, or one the application lists for that
 * issuer: a key the application knows, or a `did:key`, can sign only in its own issuer's name.
 */
final readonly class CredentialVerifier
{
    /**
     * @param array<string, list<string>> $controllers the verification methods of each issuer, by issuer identifier,
     *                                                 beyond its identifier and the fragments of it
     */
    public function __construct(
        private VerificationMethods $methods,
        private ?string $defaultMethod = null,
        private EddsaRdfc2022 $dataIntegrity = new EddsaRdfc2022(),
        private array $controllers = [],
    ) {
    }

    /**
     * @throws InvalidCredential
     */
    public function verify(string $credential, ?DateTimeImmutable $at = null): VerifiedCredential
    {
        $credential = trim($credential);
        $at ??= new DateTimeImmutable();

        if (Baked::isJwt($credential)) {
            [$header, $unverified] = JwtVerifier::peek($credential);
            $method = \is_string($header['kid'] ?? null) ? $header['kid'] : $this->defaultMethod ?? throw InvalidCredential::because('The token names no key, and the verifier has no default one.');
            $claims = new JwtVerifier(new IssuerMethods($this->methods, $unverified, $this->controllers)->key($method))->verify($credential, $at);

            if (($claims['iss'] ?? null) !== new VerifiedCredential($claims, 'jwt')->issuer()) {
                throw InvalidCredential::because('The "iss" claim of the token is not the issuer of the credential.');
            }

            unset($claims['iss'], $claims['jti'], $claims['sub'], $claims['nbf'], $claims['exp']);

            return new VerifiedCredential($claims, 'jwt');
        }

        try {
            $document = json_decode($credential, true, 64, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidCredential::because('A credential is a compact JSON Web Token or the JSON of a document with its proof.');
        }

        if (!\is_array($document) || \strlen($credential) > JwtVerifier::MAX_LENGTH) {
            throw InvalidCredential::because('A credential is a compact JSON Web Token or the JSON of a document with its proof.');
        }

        $verified = $this->dataIntegrity->verify($document, new IssuerMethods($this->methods, $document, $this->controllers));
        $this->checkPeriod($verified, $at);

        return new VerifiedCredential($verified, 'data-integrity');
    }

    /**
     * @param array<array-key, mixed> $credential
     */
    private function checkPeriod(array $credential, DateTimeImmutable $at): void
    {
        $from = $this->moment($credential['validFrom'] ?? null);
        $until = $this->moment($credential['validUntil'] ?? null);

        if (null !== $from && $at->getTimestamp() < $from) {
            throw InvalidCredential::because('The credential is not valid yet.');
        }

        if (null !== $until && $at->getTimestamp() >= $until) {
            throw InvalidCredential::because('The credential has expired.');
        }
    }

    private function moment(mixed $moment): ?int
    {
        if (null === $moment) {
            return null;
        }

        if (!\is_string($moment)) {
            throw InvalidCredential::because('The period of validity of the credential is made of RFC 3339 moments.');
        }

        $parsed = DateTimeImmutable::createFromFormat(\DATE_RFC3339, $moment);
        $parsed = false === $parsed ? DateTimeImmutable::createFromFormat(\DATE_RFC3339_EXTENDED, $moment) : $parsed;

        if (false === $parsed) {
            throw InvalidCredential::because('The period of validity of the credential is made of RFC 3339 moments.');
        }

        return $parsed->getTimestamp();
    }
}
