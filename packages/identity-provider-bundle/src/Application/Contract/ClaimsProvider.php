<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Contract;

use Phil\IdentityProviderBundle\Domain\Model\Account\Account;

/**
 * More claims of an account for userinfo, beside those of its scopes: a service tagged
 * `phil_identity_provider.claims_provider` — autoconfigured when it implements this interface — answers with the
 * claims of the scopes the client was granted, and nothing for the others. It may not answer a claim the provider sets
 * itself, such as `sub` or `email`: the provider refuses to start the answer rather than let one claim stand for
 * another.
 */
interface ClaimsProvider
{
    /**
     * @param list<string> $scopes the scopes the access token carries
     *
     * @return array<string, mixed> claims whose values encode as JSON
     */
    public function claims(Account $account, array $scopes, string $clientId): array;
}
