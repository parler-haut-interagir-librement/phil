<?php

declare(strict_types=1);

namespace Phil\SamlSp\Key;

use OpenSSLAsymmetricKey;
use Phil\SamlSp\Exception\InvalidKey;
use SensitiveParameter;

/**
 * An RSA private key in PEM, with its passphrase when it has one: it signs the requests and the logout messages of the
 * service provider, and decrypts the assertions sent to it. Neither is shown by `var_dump()` nor by a stack trace.
 */
final readonly class PrivateKey
{
    private function __construct(
        #[SensitiveParameter]
        private string $pem,
        #[SensitiveParameter]
        private ?string $passphrase,
    ) {
    }

    public static function fromPem(#[SensitiveParameter] string $pem, #[SensitiveParameter] ?string $passphrase = null): self
    {
        $key = new self($pem, $passphrase);
        $details = openssl_pkey_get_details($key->key());

        if (false === $details || \OPENSSL_KEYTYPE_RSA !== $details['type']) {
            throw InvalidKey::because('The private key is not an RSA key.');
        }

        return $key;
    }

    public function key(): OpenSSLAsymmetricKey
    {
        $key = @openssl_pkey_get_private($this->pem, $this->passphrase);

        if (false === $key) {
            throw InvalidKey::because('The private key cannot be read: it is not PEM, or its passphrase is wrong.');
        }

        return $key;
    }

    /**
     * @return array<string, never>
     */
    public function __debugInfo(): array
    {
        return [];
    }
}
