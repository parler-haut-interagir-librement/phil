<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Application\Message;

/**
 * A bounce decision sent on through Messenger, for a handler in another process: identifiers only, never the address.
 * The handler finds the address by its fingerprint — `EmailAddresses::fingerprint()` of the addresses it keeps — or by
 * the recipient identifier the application signed in the return path.
 */
final readonly class BounceDecidedMessage
{
    /**
     * @param string      $decision    a `BounceDecision` value
     * @param string      $kind        a `NoticeKind` value
     * @param string|null $fingerprint the address's fingerprint, 64 hexadecimal digits
     * @param string      $noticeKey   what names the notice, the same for the same report and address
     */
    public function __construct(
        public string $decision,
        public string $kind,
        public ?string $fingerprint,
        public ?string $recipientId,
        public string $noticeKey,
    ) {
    }
}
