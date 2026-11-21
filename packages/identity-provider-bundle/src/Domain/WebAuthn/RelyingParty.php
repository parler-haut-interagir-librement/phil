<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\WebAuthn;

use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Exception\VerificationFailed;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;

/**
 * The relying party of WebAuthn (Web Authentication, Level 3): the options of the two ceremonies, and the
 * verification of their responses.
 *
 * Registration asks for a discoverable credential — a passkey — and the attestation of the `AttestationPolicy`, none
 * by default. With the conveyance `none`, a statement an authenticator sends anyway is not read: its format is
 * recorded and nothing is trusted about the authenticator's make. With another conveyance, `AttestationStatement`
 * reads it, and the passkey records what it proved; the policy's lists and backup rule are the caller's. Sign-in checks
 * the client data, the hash of the relying party identifier, the user's presence — and verification when required —,
 * the signature over the authenticator data and the client data, and refuses a counter that does not move forward
 * when the authenticator keeps one: a cloned authenticator.
 */
final readonly class RelyingParty
{
    public const int CHALLENGE_BYTES = 32;

    public const int MAX_ATTESTATION_OBJECT = 16384;

    /**
     * @param list<string> $origins the origins the ceremonies may come from, such as `https://example.com`
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $origins,
        public bool $requireUserVerification = false,
        public int $timeout = 300_000,
        public AttestationPolicy $attestation = new AttestationPolicy(),
    ) {
        if ('' === $id || '' === $name || [] === $origins) {
            throw InvalidValue::because('A relying party has an identifier, a name and at least one origin.');
        }
    }

    public static function challenge(): string
    {
        return Base64Url::encode(random_bytes(self::CHALLENGE_BYTES));
    }

    /**
     * The options of `navigator.credentials.create()`, as `PublicKeyCredential.parseCreationOptionsFromJSON()` reads
     * them.
     *
     * @param list<string> $excludeCredentialIds the passkeys the account already has, base64url
     *
     * @return array<string, mixed>
     */
    public function creationOptions(string $challenge, string $userHandle, string $userName, string $displayName, array $excludeCredentialIds = []): array
    {
        return [
            'challenge' => $challenge,
            'rp' => ['id' => $this->id, 'name' => $this->name],
            'user' => ['id' => Base64Url::encode($userHandle), 'name' => $userName, 'displayName' => $displayName],
            'pubKeyCredParams' => array_map(static fn (SigningAlgorithm $algorithm): array => ['type' => 'public-key', 'alg' => $algorithm->cose()], [SigningAlgorithm::EdDSA, SigningAlgorithm::ES256, SigningAlgorithm::RS256]),
            'timeout' => $this->timeout,
            'excludeCredentials' => array_map(static fn (string $id): array => ['type' => 'public-key', 'id' => $id], $excludeCredentialIds),
            'authenticatorSelection' => ['residentKey' => 'required', 'requireResidentKey' => true, 'userVerification' => $this->userVerification()],
            'attestation' => $this->attestation->conveyance,
        ];
    }

    /**
     * The options of `navigator.credentials.get()`: without credentials, the browser offers the passkeys it holds for
     * the relying party.
     *
     * @param list<string> $allowCredentialIds base64url
     *
     * @return array<string, mixed>
     */
    public function requestOptions(string $challenge, array $allowCredentialIds = []): array
    {
        return [
            'challenge' => $challenge,
            'rpId' => $this->id,
            'timeout' => $this->timeout,
            'allowCredentials' => array_map(static fn (string $id): array => ['type' => 'public-key', 'id' => $id], $allowCredentialIds),
            'userVerification' => $this->userVerification(),
        ];
    }

    /**
     * @param ?int $now the time the certificates of an attestation are checked at, as a Unix timestamp; the current one
     *                  when null
     *
     * @throws MalformedInput     when a part of the response cannot be read
     * @throws VerificationFailed when it does not answer the challenge of this relying party, or its attestation does
     *                            not verify
     */
    public function verifyRegistration(string $clientDataJson, string $attestationObject, string $challenge, ?int $now = null): RegisteredCredential
    {
        ClientData::parse($clientDataJson)->assert('webauthn.create', $challenge, $this->origins);

        if (\strlen($attestationObject) > self::MAX_ATTESTATION_OBJECT) {
            throw MalformedInput::because('The attestation object is too long.');
        }

        $attestation = Cbor::decode($attestationObject);

        if (!\is_array($attestation) || !\is_string($attestation['fmt'] ?? null) || !\is_string($attestation['authData'] ?? null)) {
            throw MalformedInput::because('The attestation object has a format and authenticator data.');
        }

        $data = AuthenticatorData::parse($attestation['authData']);
        $this->assertAuthenticatorData($data);

        if (null === $data->credentialId || null === $data->coseKey || null === $data->aaguid) {
            throw VerificationFailed::because('The registration carries no credential.');
        }

        CoseKey::decode($data->coseKey);
        $type = 'none' === $this->attestation->conveyance
            ? ('none' === $attestation['fmt'] ? AttestationType::None : AttestationType::Unverified)
            : AttestationStatement::verify($attestation['fmt'], $attestation['attStmt'] ?? [], $attestation['authData'], hash('sha256', $clientDataJson, true), $data, $this->attestation->trustedRoots, $now ?? time());

        return new RegisteredCredential(
            $data->credentialId,
            $data->coseKey,
            $data->signCount,
            $data->aaguid,
            $data->has(AuthenticatorData::USER_VERIFIED),
            $data->has(AuthenticatorData::BACKUP_ELIGIBLE),
            $data->has(AuthenticatorData::BACKED_UP),
            substr($attestation['fmt'], 0, RegisteredCredential::FORMAT_MAX_LENGTH),
            $type,
        );
    }

    /**
     * @throws MalformedInput     when a part of the response cannot be read
     * @throws VerificationFailed when it does not answer the challenge, its signature does not verify, or its counter
     *                            went back
     */
    public function verifyAssertion(string $clientDataJson, string $authenticatorData, string $signature, string $challenge, string $coseKey, int $storedSignCount): VerifiedAssertion
    {
        ClientData::parse($clientDataJson)->assert('webauthn.get', $challenge, $this->origins);
        $data = AuthenticatorData::parse($authenticatorData);
        $this->assertAuthenticatorData($data);

        if (!CoseKey::decode($coseKey)->verify($authenticatorData.hash('sha256', $clientDataJson, true), $signature)) {
            throw VerificationFailed::because('The signature of the passkey does not verify.');
        }

        if (($data->signCount > 0 || $storedSignCount > 0) && $data->signCount <= $storedSignCount) {
            throw VerificationFailed::because('The counter of the passkey went back: the authenticator may be cloned.');
        }

        return new VerifiedAssertion($data->signCount, $data->has(AuthenticatorData::USER_VERIFIED), $data->has(AuthenticatorData::BACKED_UP));
    }

    private function assertAuthenticatorData(AuthenticatorData $data): void
    {
        if (!hash_equals(hash('sha256', $this->id, true), $data->rpIdHash)) {
            throw VerificationFailed::because('The response is for another relying party.');
        }

        if (!$data->has(AuthenticatorData::USER_PRESENT)) {
            throw VerificationFailed::because('The user was not present.');
        }

        if ($this->requireUserVerification && !$data->has(AuthenticatorData::USER_VERIFIED)) {
            throw VerificationFailed::because('The user was not verified.');
        }
    }

    private function userVerification(): string
    {
        return $this->requireUserVerification ? 'required' : 'preferred';
    }
}
