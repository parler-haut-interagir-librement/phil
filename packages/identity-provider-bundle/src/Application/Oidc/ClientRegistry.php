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
 * its SHA-256 — or registers the public keys it signs client assertions with, and receives no secret. Every client
 * must send a PKCE challenge, unless a confidential one is registered without. A resource it may ask tokens for is an
 * absolute `https` address without a fragment, read and kept like a redirect address. Its sector, for pairwise
 * subjects, is the host it names, or the host of its redirect addresses when they share one.
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
     * @param list<string>                    $redirectUris
     * @param list<string>                    $postLogoutRedirectUris
     * @param list<string>                    $scopes
     * @param list<array<string, mixed>>|null $jwks                   the public keys of `private_key_jwt`, in place of a secret
     * @param list<string>                    $resources              the audiences it may ask tokens for
     *
     * @return array{Client, ?string} the client and its secret, null for a public client and one with keys
     *
     * @throws Refused when the identifier is taken or out of shape, or an address, a scope or a key is refused
     */
    public function register(string $id, string $name, array $redirectUris, array $postLogoutRedirectUris = [], array $scopes = [], bool $public = false, bool $firstParty = false, bool $requirePkce = true, ?array $jwks = null, array $resources = [], ?string $backchannelLogoutUri = null, ?string $sectorIdentifier = null): array
    {
        if ($public && null !== $jwks) {
            throw Refused::because('client.invalid', 'A public client registers no keys.');
        }

        if ($this->clients->get($id) instanceof Client) {
            throw Refused::because('client.taken');
        }

        if ([] !== $unknown = array_diff($scopes, $this->settings->scopes)) {
            throw Refused::because('client.scope_unknown', ...array_values($unknown));
        }

        $secret = $public || null !== $jwks ? null : Base64Url::encode(random_bytes(32));

        $redirects = $this->addresses($redirectUris);
        $hosts = array_unique(array_map(fn (string $uri): string => Url::fromString($uri, $this->allowLocalhost)->host, $redirects));

        try {
            $client = Client::register($id, $name, $secret, $redirects, $this->addresses($postLogoutRedirectUris), $scopes, $firstParty, $this->clock->now(), $requirePkce, $jwks, $this->addresses($resources, 'client.resource_invalid'), null === $backchannelLogoutUri ? null : $this->addresses([$backchannelLogoutUri], 'client.logout_uri_invalid')[0], $sectorIdentifier ?? (1 === \count($hosts) ? array_first($hosts) : null));
        } catch (InvalidValue $invalidValue) {
            throw Refused::because('client.invalid', $invalidValue->getMessage());
        }

        $this->clients->save($client);
        $this->journal?->record(IdentityEventType::ClientRegistered, null, ['client' => $client->id(), 'public' => $public, 'pkce' => $client->requiresPkce(), 'keys' => null !== $jwks]);

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
     * Replaces the public keys of a client that signs client assertions, for a rotation: the assertions signed by a key
     * left out are refused from now on.
     *
     * @param list<array<string, mixed>> $jwks
     *
     * @throws Refused when the client has no keys, or a key is refused
     */
    public function replaceJwks(Client $client, array $jwks): void
    {
        try {
            $client->replaceJwks($jwks);
        } catch (InvalidValue $invalidValue) {
            throw Refused::because('client.invalid', $invalidValue->getMessage());
        }

        $this->clients->save($client);
        $this->journal?->record(IdentityEventType::ClientKeysReplaced, null, ['client' => $client->id()]);
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
    private function addresses(array $addresses, string $reason = 'client.redirect_invalid'): array
    {
        $canonical = [];

        foreach ($addresses as $address) {
            try {
                $url = Url::fromString($address, $this->allowLocalhost);
            } catch (InvalidUrl) {
                throw Refused::because($reason, $address);
            }

            $local = 'localhost' === $url->host || str_ends_with($url->host, '.localhost');

            if (null !== $url->fragment || ('https' !== $url->scheme && !$local)) {
                throw Refused::because($reason, $address);
            }

            $canonical[] = $url->toString();
        }

        return $canonical;
    }
}
