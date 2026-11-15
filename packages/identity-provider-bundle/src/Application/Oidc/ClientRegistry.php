<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Oidc;

use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\Journal;
use Phil\IdentityProviderBundle\Domain\Contract\ClientRepository;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Psr\Clock\ClockInterface;

/**
 * The clients registered in the database, by the console. A redirect address is an absolute `https` address read by
 * `phil/url` — `http` only on `localhost` when allowed, for development —, without a fragment, kept in its canonical
 * form, the form the client must send. A confidential client receives a secret of 256 bits, shown once and kept as
 * its SHA-256. Every client must send a PKCE challenge, unless a confidential one is registered without.
 */
final readonly class ClientRegistry
{
    public function __construct(
        private ClientRepository $clients,
        private ClockInterface $clock,
        private ProviderSettings $settings,
        private ?Journal $journal = null,
        private bool $allowLocalhost = false,
    ) {
    }

    /**
     * @param list<string> $redirectUris
     * @param list<string> $postLogoutRedirectUris
     * @param list<string> $scopes
     *
     * @return array{Client, ?string} the client and its secret, null for a public client
     *
     * @throws Refused when the identifier is taken or out of shape, or an address or a scope is refused
     */
    public function register(string $id, string $name, array $redirectUris, array $postLogoutRedirectUris = [], array $scopes = [], bool $public = false, bool $firstParty = false, bool $requirePkce = true): array
    {
        if ($this->clients->get($id) instanceof Client) {
            throw Refused::because('client.taken');
        }

        if ([] !== $unknown = array_diff($scopes, $this->settings->scopes)) {
            throw Refused::because('client.scope_unknown', ...array_values($unknown));
        }

        $secret = $public ? null : Base64Url::encode(random_bytes(32));

        try {
            $client = Client::register($id, $name, $secret, $this->addresses($redirectUris), $this->addresses($postLogoutRedirectUris), $scopes, $firstParty, $this->clock->now(), $requirePkce);
        } catch (InvalidValue $invalidValue) {
            throw Refused::because('client.invalid', $invalidValue->getMessage());
        }

        $this->clients->save($client);
        $this->journal?->record(IdentityEventType::ClientRegistered, null, ['client' => $client->id(), 'public' => $public, 'pkce' => $client->requiresPkce()]);

        return [$client, $secret];
    }

    /**
     * @return list<Client>
     */
    public function all(): array
    {
        return $this->clients->all();
    }

    public function get(string $id): ?Client
    {
        return $this->clients->get($id);
    }

    /**
     * A new secret for a confidential client, shown once; the old one stops working.
     */
    public function rotateSecret(Client $client): string
    {
        $secret = Base64Url::encode(random_bytes(32));
        $client->rotateSecret($secret);
        $this->clients->save($client);

        return $secret;
    }

    /**
     * Disables the client: it can no longer authorize nor use its tokens.
     */
    public function disable(Client $client): void
    {
        $client->disable($this->clock->now());
        $this->clients->save($client);
    }

    public function remove(Client $client): void
    {
        $this->clients->remove($client);
    }

    /**
     * @param list<string> $addresses
     *
     * @return list<string>
     */
    private function addresses(array $addresses): array
    {
        $canonical = [];

        foreach ($addresses as $address) {
            try {
                $url = Url::fromString($address, $this->allowLocalhost);
            } catch (InvalidUrl) {
                throw Refused::because('client.redirect_invalid', $address);
            }

            $local = 'localhost' === $url->host || str_ends_with($url->host, '.localhost');

            if (null !== $url->fragment || ('https' !== $url->scheme && !$local)) {
                throw Refused::because('client.redirect_invalid', $address);
            }

            $canonical[] = $url->toString();
        }

        return $canonical;
    }
}
