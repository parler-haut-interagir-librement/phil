<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequest;

/**
 * Sends the link of a request made without an account to the address it names. The secret travels in the link only:
 * an adapter sends it at once rather than through a message queue, where it would be written down.
 */
interface GuestLinkSender
{
    public function send(GuestRequest $request, string $token): void;
}
