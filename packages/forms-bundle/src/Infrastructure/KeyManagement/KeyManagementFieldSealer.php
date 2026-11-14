<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Infrastructure\KeyManagement;

use Override;
use Phil\FormsBundle\Domain\Contract\FieldSealer;
use Phil\FormsBundle\Domain\Exception\UnreadableValue;
use RuntimeException;
use SensitiveParameter;
use SodiumException;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\ExceptionInterface;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;

/**
 * Seals sensitive values by the envelope encryption of Symfony's KeyManagement component: a fresh data key per value
 * wrapped by a master key of the KMS, the value encrypted locally with AES-256-GCM, the context authenticated with it.
 *
 * A sealed value reads `phs1.<key>.<envelope>`: the master key's name and the envelope in base64url without padding,
 * the envelope's first byte, 2, naming its format. The name is checked against the one the envelope carries, and a
 * value opens under the master key it names, so values sealed before the current key changed still open while the KMS
 * holds their key. The component is experimental: this adapter is the only class of the bundle that calls it.
 */
final readonly class KeyManagementFieldSealer implements FieldSealer
{
    public const string PREFIX = 'phs1.';

    /**
     * The format byte of an envelope of the component.
     */
    public const int FORMAT = 2;

    private EnvelopeEncrypter $encrypter;

    public function __construct(
        DataKeyGeneratorInterface $kms,
        private string $keyId,
    ) {
        $this->encrypter = new EnvelopeEncrypter($kms);
    }

    /**
     * @throws KeyNotFoundException when the KMS has no master key of the configured name
     * @throws ExceptionInterface   when the KMS is unreachable or refuses
     */
    #[Override]
    public function seal(#[SensitiveParameter] string $value, string $context): string
    {
        $envelope = $this->encrypter->encrypt($this->keyId, $value, $context);

        return self::PREFIX.$this->encode($this->keyId).'.'.$this->encode(\chr(self::FORMAT).$envelope);
    }

    #[Override]
    public function open(string $sealed, string $context): string
    {
        $parts = explode('.', $sealed);

        if (3 !== \count($parts) || self::PREFIX !== $parts[0].'.') {
            throw UnreadableValue::of($context);
        }

        $keyId = $this->decode($parts[1]);
        $bytes = $this->decode($parts[2]);

        if (null === $keyId || null === $bytes || \chr(self::FORMAT) !== substr($bytes, 0, 1)) {
            throw UnreadableValue::of($context);
        }

        try {
            $envelope = Envelope::fromBytes(substr($bytes, 1));
        } catch (ExceptionInterface $exception) {
            throw UnreadableValue::of($context, $exception);
        }

        if ($envelope->keyId !== $keyId) {
            throw UnreadableValue::of($context);
        }

        try {
            return $this->encrypter->decrypt($envelope, $context);
        } catch (ExceptionInterface|RuntimeException $exception) {
            throw UnreadableValue::of($context, $exception);
        }
    }

    private function encode(string $bytes): string
    {
        return sodium_bin2base64($bytes, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    private function decode(string $text): ?string
    {
        try {
            return sodium_base642bin($text, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (SodiumException) {
            return null;
        }
    }
}
