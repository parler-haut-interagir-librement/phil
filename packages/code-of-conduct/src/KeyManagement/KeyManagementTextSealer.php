<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\KeyManagement;

use Override;
use Phil\CodeOfConduct\Contract\TextSealer;
use Phil\CodeOfConduct\Exception\UnreadableText;
use RuntimeException;
use SensitiveParameter;
use SodiumException;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\ExceptionInterface;

/**
 * Seals by the envelope encryption of Symfony's KeyManagement component: a fresh data key per text, wrapped by a
 * master key the KMS holds, the text encrypted locally with AES-256-GCM, the context authenticated with it. The sealed
 * text is the envelope in standard base64; it names its master key, so a text sealed before the configured key
 * changed still opens while the KMS holds the old key.
 *
 * The component is experimental: this adapter is the only class of the package that calls it. It keeps no state
 * between two calls.
 */
final readonly class KeyManagementTextSealer implements TextSealer
{
    private EnvelopeEncrypter $encrypter;

    public function __construct(
        DataKeyGeneratorInterface $kms,
        private string $keyId,
    ) {
        $this->encrypter = new EnvelopeEncrypter($kms);
    }

    /**
     * @throws ExceptionInterface when the KMS has no master key of the configured name, is unreachable or refuses
     */
    #[Override]
    public function seal(#[SensitiveParameter] string $plaintext, string $context): string
    {
        return sodium_bin2base64((string) $this->encrypter->encrypt($this->keyId, $plaintext, $context), \SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    #[Override]
    public function open(string $sealed, string $context): string
    {
        try {
            return $this->encrypter->decrypt(Envelope::fromBytes(sodium_base642bin($sealed, \SODIUM_BASE64_VARIANT_ORIGINAL)), $context);
        } catch (ExceptionInterface|RuntimeException|SodiumException $exception) {
            throw UnreadableText::because('A sealed text of a report does not open: it was altered, moved to another report, or sealed under a master key the KMS does not hold.', $exception);
        }
    }
}
