<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\KeyManagement;

use Override;
use Phil\LtiBundle\Application\Contract\KeySealer;
use RuntimeException;
use SensitiveParameter;
use SodiumException;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\ExceptionInterface;

/**
 * Seals the private keys by envelope encryption through Symfony's KeyManagement component, under a master key of the
 * application's KMS — a fresh data key per private key, AES-256-GCM, the key identifier as additional data — and
 * stores the envelope in base 64. The component is experimental in Symfony 8.2: this adapter is the only class that
 * calls it.
 */
final readonly class KeyManagementSealer implements KeySealer
{
    private EnvelopeEncrypter $encrypter;

    public function __construct(
        DataKeyGeneratorInterface $kms,
        private string $masterKey,
    ) {
        $this->encrypter = new EnvelopeEncrypter($kms);
    }

    #[Override]
    public function seal(string $keyId, #[SensitiveParameter] string $key): string
    {
        try {
            return sodium_bin2base64((string) $this->encrypter->encrypt($this->masterKey, $key, $this->aad($keyId)), \SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (ExceptionInterface|RuntimeException $exception) {
            throw new RuntimeException(\sprintf('The key "%s" cannot be sealed: %s', $keyId, $exception->getMessage()), 0, $exception);
        }
    }

    #[Override]
    public function open(string $keyId, string $sealed): string
    {
        try {
            $bytes = sodium_base642bin($sealed, \SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (SodiumException $sodiumException) {
            throw new RuntimeException(\sprintf('The sealed key "%s" is not in base 64.', $keyId), 0, $sodiumException);
        }

        try {
            return $this->encrypter->decrypt(Envelope::fromBytes($bytes), $this->aad($keyId));
        } catch (ExceptionInterface|RuntimeException $exception) {
            throw new RuntimeException(\sprintf('The sealed key "%s" does not open: %s', $keyId, $exception->getMessage()), 0, $exception);
        }
    }

    private function aad(string $keyId): string
    {
        return 'phil_lti.key:'.$keyId;
    }
}
