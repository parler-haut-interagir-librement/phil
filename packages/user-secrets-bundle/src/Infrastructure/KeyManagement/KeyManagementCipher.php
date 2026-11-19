<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Infrastructure\KeyManagement;

use Override;
use Phil\UserSecretsBundle\Application\Contract\EnvelopeCipher;
use Phil\UserSecretsBundle\Application\Exception\SealingFailed;
use Phil\UserSecretsBundle\Application\Exception\UnknownKey;
use Phil\UserSecretsBundle\Application\Exception\UnreadableSecret;
use Phil\UserSecretsBundle\Domain\Encryption\SealedAlgorithm;
use RuntimeException;
use SensitiveParameter;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\Envelope as KmsEnvelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\ExceptionInterface;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\LogicException;

/**
 * Envelope encryption through Symfony's KeyManagement component: a fresh data key per value wrapped by the KMS under
 * the master key, the value encrypted locally with AES-256-GCM, a self-contained envelope.
 *
 * The component is experimental: this adapter is the only class that calls it. Every failure of the KMS becomes an
 * exception of the bundle that keeps the component's, or the client's, as its previous one.
 */
final readonly class KeyManagementCipher implements EnvelopeCipher
{
    private EnvelopeEncrypter $encrypter;

    public function __construct(
        DataKeyGeneratorInterface $kms,
    ) {
        $this->encrypter = new EnvelopeEncrypter($kms);
    }

    #[Override]
    public function algorithm(): SealedAlgorithm
    {
        return SealedAlgorithm::KeyManagementAes256Gcm;
    }

    #[Override]
    public function seal(string $keyId, #[SensitiveParameter] string $plaintext, string $aad): string
    {
        try {
            return (string) $this->encrypter->encrypt($keyId, $plaintext, $aad);
        } catch (KeyNotFoundException $keyNotFoundException) {
            throw UnknownKey::current($keyId, $keyNotFoundException);
        } catch (ExceptionInterface|RuntimeException $exception) {
            throw SealingFailed::kms($keyId, $exception);
        }
    }

    #[Override]
    public function open(string $keyId, string $envelope, string $aad): string
    {
        try {
            $parsed = KmsEnvelope::fromBytes($envelope);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw UnreadableSecret::because('A sealed secret is malformed.', $invalidArgumentException);
        }

        try {
            return $this->encrypter->decrypt($parsed, $aad);
        } catch (DecryptionFailedException|LogicException $exception) {
            throw UnreadableSecret::because('A sealed secret does not open: it was altered, moved to another row, or sealed under a master key the KMS does not hold.', $exception);
        } catch (ExceptionInterface|RuntimeException $exception) {
            throw UnreadableSecret::kms($keyId, $exception);
        }
    }
}
