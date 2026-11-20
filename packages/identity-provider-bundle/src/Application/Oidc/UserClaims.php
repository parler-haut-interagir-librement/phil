<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Oidc;

use LogicException;
use Phil\IdentityProviderBundle\Application\Contract\ClaimsProvider;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;

/**
 * The claims of userinfo: those of the scopes (`Claims`), then those of each claims provider, in the order of their
 * tags. A provider that answers a claim the provider sets — `RESERVED`, or one an earlier source answered — is a mistake
 * of the application, refused by a `LogicException`.
 */
final readonly class UserClaims
{
    /** The claims of the tokens and of the scopes, which no provider answers. */
    public const array RESERVED = ['sub', 'iss', 'aud', 'exp', 'iat', 'nbf', 'jti', 'auth_time', 'nonce', 'acr', 'amr', 'azp', 'sid', 'at_hash', 'c_hash', 'idp', 'session_start', 'client_id', 'scope', 'email', 'email_verified', 'name', 'phone_number', 'phone_number_verified'];

    /**
     * @param iterable<ClaimsProvider> $providers
     */
    public function __construct(
        private iterable $providers = [],
    ) {
    }

    /**
     * @param list<string> $scopes
     * @param ?string      $subject the `sub` the client knows the account by
     *
     * @return array<string, mixed>
     *
     * @throws LogicException when a provider answers a claim already set or reserved
     */
    public function of(Account $account, array $scopes, string $clientId, ?string $subject = null): array
    {
        $claims = Claims::of($account, $scopes, $subject);

        foreach ($this->providers as $provider) {
            foreach ($provider->claims($account, $scopes, $clientId) as $name => $value) {
                if (\array_key_exists($name, $claims) || \in_array($name, self::RESERVED, true)) {
                    throw new LogicException(\sprintf('The claims provider %s answers "%s", which the provider sets.', $provider::class, $name));
                }

                $claims[$name] = $value;
            }
        }

        return $claims;
    }
}
