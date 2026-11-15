<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Secret;

use Override;
use Phil\IdentityProviderBundle\Application\Contract\SecretCipher;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use SensitiveParameter;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;

/**
 * Secrets encrypted by the KeyManagement component of Symfony under the configured key, the context as additional
 * authenticated data: `kms.<key id>.<ciphertext>`, both in base64url. A value kept in clear before the key was
 * configured still opens, so that the encryption can be turned on without a migration; it is sealed again at its
 * next write.
 */
final readonly class KeyManagementSecretCipher implements SecretCipher
{
    public const string PREFIX = 'kms.';

    public function __construct(
        private EncrypterInterface $encrypter,
        private DecrypterInterface $decrypter,
        private string $keyId,
    ) {
    }

    #[Override]
    public function seal(#[SensitiveParameter] string $plaintext, string $context): string
    {
        $ciphertext = $this->encrypter->encrypt($this->keyId, $plaintext, $context);

        return self::PREFIX.Base64Url::encode($ciphertext->keyId).'.'.Base64Url::encode($ciphertext->blob);
    }

    #[Override]
    public function open(string $sealed, string $context): string
    {
        if (str_starts_with($sealed, PlainSecretCipher::PREFIX)) {
            return substr($sealed, \strlen(PlainSecretCipher::PREFIX));
        }

        $parts = explode('.', substr($sealed, \strlen(self::PREFIX)));

        if (!str_starts_with($sealed, self::PREFIX) || 2 !== \count($parts)) {
            throw MalformedInput::because('The secret is not sealed by the key management service.');
        }

        return $this->decrypter->decrypt(new Ciphertext(Base64Url::decode($parts[1]), Base64Url::decode($parts[0])), $context);
    }
}
