<?php

declare(strict_types=1);

namespace Phil\Lti\Key;

use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use JsonException;
use Phil\Lti\Exception\InvalidToken;
use SensitiveParameter;
use Throwable;

/**
 * An RSA private key of 2048 to 8192 bits that signs with `RS256`, under a key identifier published with its public
 * half in the key set of its side: a private JSON Web Key of `web-token/jwt-library`. A dump of the object shows its
 * identifier and its public half, never the private members.
 */
final readonly class RsaPrivateKey
{
    public const int DEFAULT_BITS = 2048;

    /** The longest JSON of a private key `fromJson()` reads, in bytes. */
    public const int MAX_JSON_BYTES = 65_536;

    /** The deepest nesting of the JSON of a private key: an object of strings. */
    public const int MAX_JSON_DEPTH = 4;

    /**
     * The private members of an RSA JSON Web Key, RFC 7518 section 6.3.2.
     */
    private const array PRIVATE_MEMBERS = ['d', 'p', 'q', 'dp', 'dq', 'qi'];

    private function __construct(
        private JWK $jwk,
        public RsaPublicKey $publicKey,
    ) {
    }

    /**
     * @param string|null $keyId the identifier published in the key set; the RFC 7638 thumbprint when null
     *
     * @throws InvalidToken when the text is not an RSA private key in PEM, or the passphrase is wrong
     */
    public static function fromPem(#[SensitiveParameter] string $pem, ?string $keyId = null, #[SensitiveParameter] ?string $passphrase = null): self
    {
        try {
            $values = JWKFactory::createFromKey($pem, $passphrase)->all();
        } catch (Throwable $throwable) {
            throw InvalidToken::because('The private key is not a key in PEM, or its passphrase is wrong.', $throwable);
        }

        return self::of($values, $keyId);
    }

    /**
     * A private JSON Web Key of type `RSA`: its public members `n` and `e`, its private exponent `d`, and its primes and
     * their exponents when it has them.
     *
     * @param array<array-key, mixed> $jwk
     * @param string|null             $keyId the identifier published in the key set; the `kid` of the key when null,
     *                                       its RFC 7638 thumbprint when it has none
     *
     * @throws InvalidToken when the key is not an RSA private key of 2048 to 8192 bits
     */
    public static function fromJwk(#[SensitiveParameter] array $jwk, ?string $keyId = null): self
    {
        $kid = $jwk['kid'] ?? null;

        return self::of($jwk, $keyId ?? (\is_string($kid) ? $kid : null));
    }

    /**
     * A private JSON Web Key written as JSON, as `toJson()` writes it: 64 KiB and 4 levels at most.
     *
     * @param string|null $keyId the identifier published in the key set; the `kid` of the key when null
     *
     * @throws InvalidToken when the text is not the JSON of an RSA private key of 2048 to 8192 bits
     */
    public static function fromJson(#[SensitiveParameter] string $json, ?string $keyId = null): self
    {
        if (\strlen($json) > self::MAX_JSON_BYTES) {
            throw InvalidToken::because(\sprintf('A private JSON Web Key is %d bytes at most.', self::MAX_JSON_BYTES));
        }

        try {
            $jwk = json_decode($json, true, self::MAX_JSON_DEPTH, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidToken::because('The private key is not a JSON Web Key.', $jsonException);
        }

        return self::fromJwk(\is_array($jwk) ? $jwk : [], $keyId);
    }

    /**
     * A new key, for a rotation.
     */
    public static function generate(int $bits = self::DEFAULT_BITS, ?string $keyId = null): self
    {
        return self::of(JWKFactory::createRSAKey(max(RsaPublicKey::MIN_BITS, min(RsaPublicKey::MAX_BITS, $bits)))->all(), $keyId);
    }

    public function keyId(): string
    {
        return (string) $this->publicKey->keyId;
    }

    /**
     * The key as `web-token/jwt-library` signs with it.
     */
    public function jwk(): JWK
    {
        return $this->jwk;
    }

    /**
     * The private JSON Web Key, its identifier, algorithm and use with it, to be sealed by whoever stores it.
     *
     * @return array<string, string>
     */
    public function toJwk(): array
    {
        /** @var array<string, string> */
        return $this->jwk->all();
    }

    /**
     * The private JSON Web Key as JSON, to be sealed by whoever stores it; `fromJson()` reads it back.
     */
    public function toJson(): string
    {
        return json_encode($this->jwk->all(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array{keyId: string, publicKey: RsaPublicKey}
     */
    public function __debugInfo(): array
    {
        return ['keyId' => $this->keyId(), 'publicKey' => $this->publicKey];
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function of(#[SensitiveParameter] array $values, ?string $keyId): self
    {
        if ('RSA' !== ($values['kty'] ?? null) || !\is_string($values['d'] ?? null) || '' === $values['d']) {
            throw InvalidToken::because('The key is not an RSA private key.');
        }

        $private = [];

        foreach (self::PRIVATE_MEMBERS as $member) {
            if (isset($values[$member])) {
                $private[$member] = \is_string($values[$member]) ? $values[$member] : throw InvalidToken::because(\sprintf('The member "%s" of an RSA private key is a string.', $member));
            }
        }

        $public = RsaPublicKey::fromValues($values, null === $keyId || '' === $keyId ? null : $keyId);

        if (null === $public->keyId) {
            $public = RsaPublicKey::fromValues($values, $public->thumbprint());
        }

        return new self(new JWK($public->toJwk() + $private), $public);
    }
}
