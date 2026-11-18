<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Verification;

use DateTimeImmutable;
use JsonException;
use Phil\OpenBadges\Baking\Baked;
use Phil\OpenBadges\Contract\VerificationMethods;
use Phil\OpenBadges\Credentials;
use Phil\OpenBadges\DataIntegrity\EddsaRdfc2022;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Jwt\JwtSigner;
use Phil\OpenBadges\Jwt\JwtVerifier;

/**
 * Verifies a credential however it is secured: a compact JSON Web Token, through the key its `kid` names — or the
 * default method when it names none —, or the JSON of a document with an `eddsa-rdfc-2022` proof, through its
 * verification method. Then that the key is one of the credential's issuer, and, at the moment given — the current
 * time when none is —, that the credential is valid already and not expired.
 *
 * Both forms follow one rule of validity: the credential's `validFrom` and `validUntil`, strict moments of RFC 3339.
 * A token's registered claims are typed and agree with the fields they mirror: `iss` is the issuer, and, when present,
 * `jti` the identifier, `sub` the subject's identifier, `nbf` and `exp` the seconds of `validFrom` and `validUntil`.
 * The input is at most `JwtVerifier::MAX_LENGTH` bytes, checked before anything parses it.
 *
 * The keys come from the verification methods the application gives — nothing is fetched but by them —, and a key a
 * token carries in its own header is never trusted. A verification method is one of an issuer when it is the issuer's identifier, a
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
        if (\strlen($credential) > JwtVerifier::MAX_LENGTH) {
            throw InvalidCredential::because(\sprintf('A credential is at most %d bytes.', JwtVerifier::MAX_LENGTH));
        }

        $credential = trim($credential);
        $at ??= new DateTimeImmutable();

        if (Baked::isJwt($credential)) {
            [$header, $unverified] = JwtVerifier::peek($credential);
            $method = \is_string($header['kid'] ?? null) ? $header['kid'] : $this->defaultMethod ?? throw InvalidCredential::because('The token names no key, and the verifier has no default one.');
            $claims = new JwtVerifier(new IssuerMethods($this->methods, $unverified, $this->controllers)->key($method))->verify($credential, $at);

            $this->checkClaims($claims);
            $credential = array_diff_key($claims, array_flip(JwtSigner::CLAIMS));
            $this->checkPeriod($credential, $at);

            return new VerifiedCredential($credential, 'jwt');
        }

        try {
            $document = json_decode($credential, true, 64, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidCredential::because('A credential is a compact JSON Web Token or the JSON of a document with its proof.');
        }

        if (!\is_array($document)) {
            throw InvalidCredential::because('A credential is a compact JSON Web Token or the JSON of a document with its proof.');
        }

        $verified = $this->dataIntegrity->verify($document, new IssuerMethods($this->methods, $document, $this->controllers));
        $this->checkPeriod($verified, $at);

        return new VerifiedCredential($verified, 'data-integrity');
    }

    /**
     * The registered claims of a token against the fields of its credential: `iss` always, the others when present.
     *
     * @param array<array-key, mixed> $claims
     */
    private function checkClaims(array $claims): void
    {
        $credential = new VerifiedCredential($claims, 'jwt');
        $subject = $claims['credentialSubject'] ?? null;
        $mirrored = [
            'iss' => [$credential->issuer(), 'issuer'],
            'jti' => [$claims['id'] ?? null, 'id'],
            'sub' => [\is_array($subject) ? $subject['id'] ?? null : null, 'id of the subject'],
        ];

        foreach ($mirrored as $claim => [$field, $name]) {
            $value = $claims[$claim] ?? null;

            if (('iss' === $claim || null !== $value) && (!\is_string($value) || $value !== $field)) {
                throw InvalidCredential::because(\sprintf('The "%s" claim of the token is not the %s of the credential.', $claim, $name));
            }
        }

        foreach (['nbf' => 'validFrom', 'exp' => 'validUntil'] as $claim => $field) {
            $value = $claims[$claim] ?? null;
            $moment = $this->moment($claims[$field] ?? null);

            // The claim counts whole seconds, perhaps with a fraction; the field may carry a fraction too.
            if (null !== $value && (!\is_int($value) && !\is_float($value) || !$moment instanceof DateTimeImmutable || (int) floor($value) !== $moment->getTimestamp())) {
                throw InvalidCredential::because(\sprintf('The "%s" claim of the token is not the %s of the credential.', $claim, $field));
            }
        }
    }

    /**
     * @param array<array-key, mixed> $credential
     */
    private function checkPeriod(array $credential, DateTimeImmutable $at): void
    {
        $from = $this->moment($credential['validFrom'] ?? null);
        $until = $this->moment($credential['validUntil'] ?? null);

        if ($from instanceof DateTimeImmutable && $at < $from) {
            throw InvalidCredential::because('The credential is not valid yet.');
        }

        if ($until instanceof DateTimeImmutable && $at >= $until) {
            throw InvalidCredential::because('The credential has expired.');
        }
    }

    private function moment(mixed $moment): ?DateTimeImmutable
    {
        if (null === $moment) {
            return null;
        }

        return (\is_string($moment) ? Credentials::parseMoment($moment) : null)
            ?? throw InvalidCredential::because('The period of validity of the credential is made of RFC 3339 moments.');
    }
}
