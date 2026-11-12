<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Infrastructure\KeyManagement;

use Override;
use Phil\FormsAutosave\Application\Contract\DraftSealer;
use RuntimeException;
use SensitiveParameter;
use SodiumException;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\ExceptionInterface;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;

/**
 * Seals drafts by the envelope encryption of Symfony's KeyManagement component: a fresh data key per draft wrapped by
 * a master key of the KMS, the content encrypted locally with AES-256-GCM, the context authenticated with it. The
 * sealed text is the envelope in standard base64; it carries the master key's name, so a draft sealed before the
 * configured key changed still opens while the KMS holds its key.
 *
 * The component is experimental: this adapter is the only class of the bundle that calls it.
 */
final readonly class KeyManagementSealer implements DraftSealer
{
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
    public function seal(#[SensitiveParameter] string $content, string $context): string
    {
        return sodium_bin2base64((string) $this->encrypter->encrypt($this->keyId, $content, $context), \SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    #[Override]
    public function open(string $sealed, string $context): ?string
    {
        try {
            return $this->encrypter->decrypt(Envelope::fromBytes(sodium_base642bin($sealed, \SODIUM_BASE64_VARIANT_ORIGINAL)), $context);
        } catch (ExceptionInterface|RuntimeException|SodiumException) {
            return null;
        }
    }
}
