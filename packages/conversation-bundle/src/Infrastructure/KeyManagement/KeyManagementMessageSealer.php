<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Infrastructure\KeyManagement;

use Override;
use Phil\ConversationBundle\Application\Contract\MessageSealer;
use Phil\ConversationBundle\Domain\Exception\UnreadableMessage;
use RuntimeException;
use SensitiveParameter;
use SodiumException;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\ExceptionInterface;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\SelfContainedFormat;

/**
 * Seals the subjects and bodies by the envelope encryption of Symfony's KeyManagement component: a fresh data key per
 * text, wrapped by a master key of the key management service, the text encrypted locally with AES-256-GCM.
 *
 * A sealed text reads `pcv1.a256gcm.<key>.<envelope>`: the version of the format, the identifier of its algorithm, the
 * master key's name and the component's self-contained envelope, both in base64url without padding. The version and
 * the algorithm are authenticated with the context, so that neither can be swapped; the key's name is checked against
 * the one the envelope carries. A text opens under the master key it names, so that the texts sealed before the current
 * key changed still open while the service holds their key. The component is experimental: this adapter is the only
 * class of the bundle that calls it.
 */
final readonly class KeyManagementMessageSealer implements MessageSealer
{
    public const string VERSION = 'pcv1';

    public const string ALGORITHM = 'a256gcm';

    private EnvelopeEncrypter $encrypter;

    public function __construct(
        DataKeyGeneratorInterface $kms,
        private string $keyId,
    ) {
        $this->encrypter = new EnvelopeEncrypter($kms);
    }

    /**
     * @throws KeyNotFoundException when the service has no master key of the configured name
     * @throws ExceptionInterface   when the service is unreachable or refuses
     */
    #[Override]
    public function seal(#[SensitiveParameter] string $text, string $context): string
    {
        $envelope = $this->encrypter->encrypt($this->keyId, $text, $this->aad($context));

        return self::VERSION.'.'.self::ALGORITHM.'.'.$this->encode($this->keyId).'.'.$this->encode((string) $envelope);
    }

    #[Override]
    public function open(string $sealed, string $context): string
    {
        $parts = explode('.', $sealed);

        if (4 !== \count($parts) || self::VERSION !== $parts[0] || self::ALGORITHM !== $parts[1]) {
            throw UnreadableMessage::of($context);
        }

        $keyId = $this->decode($parts[2]);
        $bytes = $this->decode($parts[3]);

        if (null === $keyId || null === $bytes || '' === $bytes || SelfContainedFormat::ID !== \ord($bytes[0])) {
            throw UnreadableMessage::of($context);
        }

        try {
            $envelope = Envelope::fromBytes($bytes);
        } catch (ExceptionInterface $exception) {
            throw UnreadableMessage::of($context, $exception);
        }

        if ($envelope->keyId !== $keyId) {
            throw UnreadableMessage::of($context);
        }

        try {
            return $this->encrypter->decrypt($envelope, $this->aad($context));
        } catch (ExceptionInterface|RuntimeException $exception) {
            throw UnreadableMessage::of($context, $exception);
        }
    }

    #[Override]
    public function currentKey(): string
    {
        return $this->keyId;
    }

    private function aad(string $context): string
    {
        return self::VERSION.'.'.self::ALGORITHM."\n".$context;
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
