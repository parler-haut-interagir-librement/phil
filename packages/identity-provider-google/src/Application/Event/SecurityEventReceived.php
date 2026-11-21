<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Application\Event;

use Phil\IdentityProviderGoogle\Domain\Risc\SecurityEventType;

/**
 * A security event acted on, dispatched once per event of a token after the receiver did its part: the provider, the
 * type, the account its subject is linked to — null when it is linked to none, or the event names no subject —, the
 * reason it gives and the sessions closed. Identifiers only: a listener reads the rest from its stores.
 */
final readonly class SecurityEventReceived
{
    public function __construct(
        public string $provider,
        public SecurityEventType $type,
        public ?string $accountId,
        public ?string $reason,
        public int $sessionsClosed,
    ) {
    }
}
