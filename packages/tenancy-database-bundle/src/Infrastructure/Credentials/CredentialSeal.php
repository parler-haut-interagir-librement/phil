<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Credentials;

use InvalidArgumentException;
use JsonException;
use Phil\TenancyDatabaseBundle\Domain\ValueObject\DatabaseCredentials;
use SensitiveParameter;
use SodiumException;
use UnexpectedValueException;

/**
 * Seals the credentials of a dedicated database for storage, and opens them again: XChaCha20-Poly1305 of libsodium,
 * under a 32-byte key the application keeps out of the database.
 *
 *     ptdb.v1.xchacha20poly1305.<base64url(nonce ‖ ciphertext)>
 *
 * - **Named and versioned.** Each sealed value starts with its format, its version and its algorithm: a value of
 *   another format, version or algorithm is refused, never read as this one.
 * - **Bound to its database.** The name of the database is authenticated with the value: credentials sealed for one
 *   database do not open for another, even with the same key.
 * - **Opened only when needed.** The credentials come back as {@see DatabaseCredentials}, which is never logged nor
 *   serialized.
 */
final readonly class CredentialSeal
{
    public const string PREFIX = 'ptdb.v1.xchacha20poly1305.';

    /**
     * The longest sealed value opened: credentials are a few hundred bytes, a longer value is not one.
     */
    public const int MAX_LENGTH = 4096;

    /**
     * @throws InvalidArgumentException when the key is not 32 bytes
     */
    public function __construct(
        #[SensitiveParameter]
        private string $key,
    ) {
        if (\SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES !== \strlen($key)) {
            throw new InvalidArgumentException(\sprintf('The key of the credential seal is %d bytes.', \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
        }
    }

    public function seal(string $database, DatabaseCredentials $credentials): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $plain = json_encode(['u' => $credentials->user, 'p' => $credentials->password, 'h' => $credentials->host, 'o' => $credentials->port], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        $sealed = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, $this->additionalData($database), $nonce, $this->key);
        sodium_memzero($plain);

        return self::PREFIX.sodium_bin2base64($nonce.$sealed, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /**
     * @throws UnexpectedValueException when the value is not of this format, was sealed for another database or under
     *                                  another key, or was altered
     */
    public function open(string $database, #[SensitiveParameter] string $sealed): DatabaseCredentials
    {
        if (\strlen($sealed) > self::MAX_LENGTH || !str_starts_with($sealed, self::PREFIX)) {
            throw new UnexpectedValueException(\sprintf('The sealed credentials are not of the format %s.', rtrim(self::PREFIX, '.')));
        }

        try {
            $binary = sodium_base642bin(substr($sealed, \strlen(self::PREFIX)), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (SodiumException) {
            throw new UnexpectedValueException('The sealed credentials are not valid base64url.');
        }

        $nonceLength = \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        // A nonce and the authentication tag at least: a shorter value cannot be a sealed one.
        $plain = \strlen($binary) >= $nonceLength + \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES
            ? sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($binary, $nonceLength), $this->additionalData($database), substr($binary, 0, $nonceLength), $this->key)
            : false;

        if (false === $plain) {
            throw new UnexpectedValueException(\sprintf('The sealed credentials do not open for the database "%s": another database, another key, or altered.', $database));
        }

        try {
            $values = json_decode($plain, true, 2, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new UnexpectedValueException('The sealed credentials do not hold credentials.');
        } finally {
            sodium_memzero($plain);
        }

        if (!\is_array($values) || !\is_string($values['u'] ?? null) || !\is_string($values['p'] ?? null) || !\is_string($values['h'] ?? null) && null !== ($values['h'] ?? null) || !\is_int($values['o'] ?? null) && null !== ($values['o'] ?? null)) {
            throw new UnexpectedValueException('The sealed credentials do not hold credentials.');
        }

        return new DatabaseCredentials($values['u'], $values['p'], $values['h'] ?? null, $values['o'] ?? null);
    }

    private function additionalData(string $database): string
    {
        return self::PREFIX.$database;
    }
}
