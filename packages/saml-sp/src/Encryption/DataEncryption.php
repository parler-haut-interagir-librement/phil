<?php

declare(strict_types=1);

namespace Phil\SamlSp\Encryption;

use Phil\SamlSp\Exception\DecryptionFailed;

/**
 * The block ciphers of an encrypted assertion: AES in GCM, and in CBC, which Microsoft Entra ID and Okta send by
 * default. A CBC assertion must be signed inside, and every decryption failure reads the same.
 */
enum DataEncryption: string
{
    case Aes128Cbc = 'http://www.w3.org/2001/04/xmlenc#aes128-cbc';
    case Aes192Cbc = 'http://www.w3.org/2001/04/xmlenc#aes192-cbc';
    case Aes256Cbc = 'http://www.w3.org/2001/04/xmlenc#aes256-cbc';
    case Aes128Gcm = 'http://www.w3.org/2009/xmlenc11#aes128-gcm';
    case Aes192Gcm = 'http://www.w3.org/2009/xmlenc11#aes192-gcm';
    case Aes256Gcm = 'http://www.w3.org/2009/xmlenc11#aes256-gcm';

    public static function fromUri(string $uri): self
    {
        return self::tryFrom($uri) ?? throw DecryptionFailed::because(\sprintf('The encryption algorithm "%s" is refused.', $uri));
    }

    public function keyLength(): int
    {
        return match ($this) {
            self::Aes128Cbc, self::Aes128Gcm => 16,
            self::Aes192Cbc, self::Aes192Gcm => 24,
            self::Aes256Cbc, self::Aes256Gcm => 32,
        };
    }

    public function isGcm(): bool
    {
        return \in_array($this, [self::Aes128Gcm, self::Aes192Gcm, self::Aes256Gcm], true);
    }

    /**
     * The cipher `openssl_decrypt()` knows.
     */
    public function cipher(): string
    {
        return \sprintf('aes-%d-%s', $this->keyLength() * 8, $this->isGcm() ? 'gcm' : 'cbc');
    }
}
