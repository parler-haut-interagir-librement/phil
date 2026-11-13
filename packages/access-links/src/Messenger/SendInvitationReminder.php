<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Messenger;

/**
 * The message that asks for the due reminder of one invitation. It carries the invitation's identifier and nothing
 * else: no recipient, no address, no link, which the handler issues fresh when it runs.
 */
final readonly class SendInvitationReminder
{
    public function __construct(
        public string $invitationId,
    ) {
    }
}
