<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\EmailAddressBundle\Domain\NoticeKind;
use Phil\EmailAddressBundle\Domain\NoticeSource;
use SensitiveParameter;

/**
 * One address a bounce speaks of, and what it says: the kind of notice, the report it came in, whether its sender was
 * authenticated, and the recipient identifier the application signed in the return path or the message metadata.
 *
 * The address stays in the process: what leaves it — a message, a log — carries the fingerprint and the identifier.
 */
final readonly class BounceNotice
{
    /**
     * @param string      $reportKey     what names the report: the same report received twice has the same key
     * @param string|null $address       the address as the report gives it, or `null` when it names none
     * @param bool        $authenticated whether the sender of a complaint was vouched for: an authenticated abuse
     *                                   report, or a provider's webhook whose signature the Webhook component checked
     * @param string|null $recipientId   the identifier the application gave the recipient, read from a signed return path
     *                                   or from the message metadata
     */
    public function __construct(
        public NoticeKind $kind,
        public NoticeSource $source,
        public string $reportKey,
        #[SensitiveParameter]
        public ?string $address,
        public ?AddressFingerprint $fingerprint,
        public bool $authenticated,
        public DateTimeImmutable $receivedAt,
        public ?string $recipientId = null,
    ) {
    }

    /**
     * What names this notice among all others: its report and its address. A report about two recipients gives two
     * notices, each decided once.
     */
    public function key(): string
    {
        return hash('sha256', $this->reportKey."\n".($this->fingerprint->value ?? '')."\n".($this->recipientId ?? ''));
    }

    /**
     * What the soft bounces of the address are counted under: its fingerprint, else its recipient identifier, or
     * `null` when the notice names neither.
     */
    public function subject(): ?string
    {
        if ($this->fingerprint instanceof AddressFingerprint) {
            return $this->fingerprint->value;
        }

        return null === $this->recipientId ? null : 'recipient:'.hash('sha256', $this->recipientId);
    }
}
