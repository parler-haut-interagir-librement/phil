<?php

declare(strict_types=1);

namespace Phil\Backup\Encryption;

use Phil\Backup\Exception\InvalidBackupValue;
use SensitiveParameter;
use SodiumException;

/**
 * The secret key backups are encrypted with: 32 random bytes, written in base64 where the application keeps its
 * secrets. Whoever holds it reads every backup; whoever loses it reads none.
 */
final readonly class BackupKey
{
    private function __construct(
        #[SensitiveParameter]
        public string $bytes,
    ) {
    }

    public static function fromBase64(#[SensitiveParameter] string $encoded): self
    {
        try {
            $bytes = sodium_base642bin(trim($encoded), \SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (SodiumException) {
            $bytes = '';
        }

        if (\SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES !== \strlen($bytes)) {
            throw InvalidBackupValue::because(\sprintf('A backup key is %d random bytes in base64, such as the output of base64_encode(random_bytes(%1$d)).', \SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES));
        }

        return new self($bytes);
    }

    public static function generate(): self
    {
        return new self(sodium_crypto_secretstream_xchacha20poly1305_keygen());
    }

    public function toBase64(): string
    {
        return sodium_bin2base64($this->bytes, \SODIUM_BASE64_VARIANT_ORIGINAL);
    }
}
