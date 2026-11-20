<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Messenger;

use Override;
use Phil\IdentityProviderBundle\Application\Contract\LogoutNotifier;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatches one `SendLogoutToken` per client on the bus; the application routes it to an asynchronous transport, whose
 * retries try a client that did not answer again.
 */
final readonly class MessengerLogoutNotifier implements LogoutNotifier
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    #[Override]
    public function sessionEnded(string $sessionId, string $accountId, array $clientIds): void
    {
        foreach ($clientIds as $clientId) {
            $this->bus->dispatch(new SendLogoutToken($clientId, $accountId, $sessionId));
        }
    }
}
