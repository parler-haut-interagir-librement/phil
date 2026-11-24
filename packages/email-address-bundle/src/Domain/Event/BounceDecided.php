<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Domain\Event;

use Phil\EmailAddressBundle\Domain\BounceDecision;
use Phil\EmailAddressBundle\Domain\ValueObject\BounceNotice;

/**
 * Dispatched, through the event dispatcher, once the bounce rules decided what a notice does to its address. A
 * newsletter, a contact list or the application listens to it and applies the decision to its own records.
 *
 * The event stays in the process and carries the address; a listener that sends it on through Messenger sends the
 * fingerprint and the recipient identifier only, as `BounceDecidedMessage` does.
 */
final readonly class BounceDecided
{
    /**
     * @param int $softBounces the soft bounces counted within the window, this one included; 0 for another kind
     */
    public function __construct(
        public BounceNotice $notice,
        public BounceDecision $decision,
        public int $softBounces = 0,
    ) {
    }
}
