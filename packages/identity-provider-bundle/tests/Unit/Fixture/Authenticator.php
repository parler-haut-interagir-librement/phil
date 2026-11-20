<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use Closure;
use OpenSSLAsymmetricKey;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;

/**
 * A software authenticator for the tests: a P-256 key, a credential identifier and a counter, answering the two
 * WebAuthn ceremonies as a browser would serialize them with `PublicKeyCredential.toJSON()`.
 */
final class Authenticator
{
    public string $credentialId;

    public int $counter = 0;

    private readonly OpenSSLAsymmetricKey $key;

    public function __construct(
        public string $rpId = 'example.com',
        public string $origin = 'https://example.com',
        ?string $credentialId = null,
        public bool $counts = true,
        public int $flags = 0x05,
    ) {
        $key = openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        \assert($key instanceof OpenSSLAsymmetricKey);
        $this->key = $key;
        $this->credentialId = $credentialId ?? random_bytes(16);
    }

    public function coseKey(): string
    {
        $details = openssl_pkey_get_details($this->key);
        \assert(\is_array($details) && \is_array($details['ec']) && \is_string($details['ec']['x']) && \is_string($details['ec']['y']));

        return Cbor::encode([1 => 2, 3 => -7, -1 => 1, -2 => Cbor::bytes(str_pad($details['ec']['x'], 32, "\0", \STR_PAD_LEFT)), -3 => Cbor::bytes(str_pad($details['ec']['y'], 32, "\0", \STR_PAD_LEFT))]);
    }

    public function authenticatorData(int $flags, ?string $attested = null): string
    {
        return hash('sha256', $this->rpId, true).\chr($flags & 0xFF).pack('N', $this->counter).($attested ?? '');
    }

    public function clientData(string $type, string $challenge, ?string $origin = null): string
    {
        return json_encode(['type' => $type, 'challenge' => $challenge, 'origin' => $origin ?? $this->origin, 'crossOrigin' => false], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * The JSON of a registration answering the challenge.
     */
    public function register(string $challenge, string $format = 'none'): string
    {
        $attested = random_bytes(16).pack('n', \strlen($this->credentialId)).$this->credentialId.$this->coseKey();
        $authData = $this->authenticatorData($this->flags | 0x40, $attested);

        return $this->json([
            'clientDataJSON' => Base64Url::encode($this->clientData('webauthn.create', $challenge)),
            'attestationObject' => Base64Url::encode(Cbor::encode(['fmt' => $format, 'attStmt' => [], 'authData' => Cbor::bytes($authData)])),
        ]);
    }

    /**
     * The JSON of a registration answering the challenge with an attestation statement, made from the bytes it signs:
     * the authenticator data and the hash of the client data.
     *
     * @param Closure(string, self): mixed $statement
     */
    public function registerAttested(string $challenge, Closure $statement, string $format = 'packed', ?string $aaguid = null): string
    {
        $attested = ($aaguid ?? random_bytes(16)).pack('n', \strlen($this->credentialId)).$this->credentialId.$this->coseKey();
        $authData = $this->authenticatorData($this->flags | 0x40, $attested);
        $clientData = $this->clientData('webauthn.create', $challenge);

        return $this->json([
            'clientDataJSON' => Base64Url::encode($clientData),
            'attestationObject' => Base64Url::encode(Cbor::encode(['fmt' => $format, 'attStmt' => $statement($authData.hash('sha256', $clientData, true), $this), 'authData' => Cbor::bytes($authData)])),
        ]);
    }

    /**
     * The signature of the passkey's own key over the bytes, as a self attestation carries it.
     */
    public function signature(string $bytes): string
    {
        openssl_sign($bytes, $signature, $this->key, \OPENSSL_ALGO_SHA256);
        \assert(\is_string($signature));

        return $signature;
    }

    /**
     * The JSON of a sign-in answering the challenge, the counter moved forward.
     */
    public function sign(string $challenge, ?string $userHandle = null): string
    {
        if ($this->counts) {
            ++$this->counter;
        }

        $authData = $this->authenticatorData($this->flags);
        $clientData = $this->clientData('webauthn.get', $challenge);
        openssl_sign($authData.hash('sha256', $clientData, true), $signature, $this->key, \OPENSSL_ALGO_SHA256);
        \assert(\is_string($signature));

        return $this->json(array_filter([
            'clientDataJSON' => Base64Url::encode($clientData),
            'authenticatorData' => Base64Url::encode($authData),
            'signature' => Base64Url::encode($signature),
            'userHandle' => null === $userHandle ? null : Base64Url::encode($userHandle),
        ], static fn (?string $value): bool => null !== $value));
    }

    /**
     * The raw parts of a sign-in, for the relying party itself.
     *
     * @return array{string, string, string}
     */
    public function assertion(string $challenge): array
    {
        if ($this->counts) {
            ++$this->counter;
        }

        $authData = $this->authenticatorData($this->flags);
        $clientData = $this->clientData('webauthn.get', $challenge);
        openssl_sign($authData.hash('sha256', $clientData, true), $signature, $this->key, \OPENSSL_ALGO_SHA256);
        \assert(\is_string($signature));

        return [$clientData, $authData, $signature];
    }

    /**
     * @param array<string, string> $response
     */
    private function json(array $response): string
    {
        return json_encode(['id' => Base64Url::encode($this->credentialId), 'rawId' => Base64Url::encode($this->credentialId), 'type' => 'public-key', 'response' => $response], \JSON_THROW_ON_ERROR);
    }
}
