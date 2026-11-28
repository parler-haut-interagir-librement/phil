<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Signature;

use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use SensitiveParameter;

/**
 * The secret half of an Ed25519 key: the console signs its requests with it, an agent its responses. It is read
 * from the configuration — an environment variable or a secret — and never written anywhere by the bundle.
 */
final readonly class SigningKey
{
    /**
     * @param non-empty-string $secret
     */
    private function __construct(
        #[SensitiveParameter]
        private string $secret,
    ) {
    }

    public static function generate(): self
    {
        return new self(sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair()));
    }

    /**
     * @param string $text the base64 of the 32 bytes of a seed, or of the 64 bytes of a secret key
     *
     * @throws InvalidValue when it is neither
     */
    public static function fromBase64(#[SensitiveParameter] string $text): self
    {
        $bytes = Base64Url::decodeKey($text);

        if (null !== $bytes && \SODIUM_CRYPTO_SIGN_SEEDBYTES === \strlen($bytes)) {
            return new self(sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($bytes)));
        }

        if (null === $bytes || \SODIUM_CRYPTO_SIGN_SECRETKEYBYTES !== \strlen($bytes)) {
            throw InvalidValue::because('A private key is the base64 of the 32 bytes of an Ed25519 seed or of the 64 bytes of its secret key.');
        }

        return new self($bytes);
    }

    /**
     * The signature of the message, in base64url.
     */
    public function sign(string $message): string
    {
        return Base64Url::encode(sodium_crypto_sign_detached($message, $this->secret));
    }

    public function verifyingKey(): VerifyingKey
    {
        return VerifyingKey::fromBytes(sodium_crypto_sign_publickey_from_secretkey($this->secret));
    }

    public function toBase64(): string
    {
        return Base64Url::encode($this->secret);
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['secret' => '***'];
    }
}
