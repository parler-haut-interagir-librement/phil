<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Event;

/**
 * An event of the bundle, dispatched through the PSR-14 dispatcher: its type, the account, and identifiers — a
 * passkey, a session, a client, a provider —, never an address, a name, a code or a secret. A listener that needs
 * more reads it from the stores by these identifiers.
 */
final readonly class IdentityEvent
{
    /**
     * @param array<string, bool|int|string|null> $context identifiers and flags
     */
    public function __construct(
        public IdentityEventType $type,
        public ?string $accountId,
        public array $context = [],
    ) {
    }
}
