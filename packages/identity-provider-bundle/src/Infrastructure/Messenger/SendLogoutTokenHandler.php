<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Messenger;

use Phil\IdentityProviderBundle\Application\Exception\DeliveryFailed;
use Phil\IdentityProviderBundle\Application\Oidc\LogoutTokens;
use Phil\IdentityProviderBundle\Domain\Contract\ClientRepository;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Posts the logout token of a session to the back-channel logout address of its client (OpenID Connect Back-Channel
 * Logout 1.0, section 2.5): `logout_token` in a form body, no redirect followed, within the timeout. A client that was
 * removed, disabled, or no longer registers an address is skipped; an answer other than 200 or 204, or no answer,
 * throws `DeliveryFailed`, so that Messenger tries again with its retry strategy.
 */
final readonly class SendLogoutTokenHandler
{
    public function __construct(
        private ClientRepository $clients,
        private LogoutTokens $tokens,
        private HttpClientInterface $http,
        private float $timeout = 5.0,
    ) {
    }

    /**
     * @throws DeliveryFailed
     */
    public function __invoke(SendLogoutToken $message): void
    {
        $client = $this->clients->get($message->clientId);
        $address = $client?->backchannelLogoutUri();

        if (!$client instanceof Client || $client->isDisabled() || null === $address) {
            return;
        }

        try {
            $status = $this->http->request('POST', $address, [
                'body' => ['logout_token' => $this->tokens->issue($client, $message->accountId, $message->sessionId)],
                'max_redirects' => 0,
                'timeout' => $this->timeout,
                'max_duration' => $this->timeout,
            ])->getStatusCode();
        } catch (ExceptionInterface) {
            $status = 0;
        }

        if (200 !== $status && 204 !== $status) {
            throw DeliveryFailed::because(\sprintf('The back-channel logout of client "%s" answered %d.', $client->id(), $status));
        }
    }
}
