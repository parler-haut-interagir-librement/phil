# Extension points

What an application implements, configures, replaces or relies on.

## A store

Any service implementing `Phil\ApiKeysBundle\Domain\Contract\ApiTokenRepository`, named in `store`, replaces the
bundle's: tokens on another database API, behind a cache. It keeps the promises the bundle's contract tests check —
`find()` the newest first, then by identifier, within the limit; `recordUse()` writing the last use alone, without
flushing anything else; `purge()` removing what expired or was revoked strictly before the instant and returning how
many.

```yaml
phil_api_keys:
    store: App\Security\CachedTokenRepository
```

## The owner types

`owner_types` declares the owners a token may act for besides the members: for each, the user provider — any
`UserProviderInterface` service — that loads an owner by its identifier when its token authenticates, and the scopes
its tokens may hold. The provider gives the security token the application's own user object for an organisation or
an application. `Phil\ApiKeysBundle\Application\OwnerTypes`, a public service, lists them.

## Who manages another owner's tokens

The page of another owner's tokens asks the application's voters for `TokenPageController::MANAGE`
(`PHIL_API_KEYS_MANAGE`) on the `TokenOwner`; nobody is granted it without a voter of the application. See
[The members' page](members-page.md#another-owners-tokens).

## An address anonymizer

A `Phil\ApiKeysBundle\Application\Contract\ClientAddressAnonymizer`, named in `last_used_address`, says what the row
keeps of the client's address, at most 100 bytes, or `null` to keep nothing:

```php
use Phil\ApiKeysBundle\Application\Contract\ClientAddressAnonymizer;

final readonly class CountryOnly implements ClientAddressAnonymizer
{
    public function __construct(private CountryLookup $countries)
    {
    }

    public function anonymize(string $address): ?string
    {
        return $this->countries->countryOf($address);
    }
}
```

`Infrastructure\IpAnonymizer\AnonymizedClientAddress` wraps any strategy of `phil/ip-anonymizer`.

## The answers to a refused token

The `401` is Symfony's: the `access_token` authenticator answers it, as plain text, or the firewall's `entry_point` and
the authenticator's `failure_handler` when they are named. `phil_api_keys.problem_responder` is one of each, as
problem details, with `phil/api-contract-bundle`. An application that answers in its own format names its own
`AuthenticationEntryPointInterface` and `AuthenticationFailureHandlerInterface` there, keeping the `WWW-Authenticate`
challenge of RFC 6750 and never saying why a token was refused.

## The events

`TokenCreated`, `TokenRevoked`, `TokenUsed` and `TokenLeaked`, dispatched through the PSR-14 dispatcher, each with the token's
identifier: see [Integrations](integrations.md#the-events-for-an-audit-trail).

## The keys of GitHub's secret scanning

`http_client` and `cache` name the services `GitHubPublicKeys` fetches and keeps GitHub's keys with: a client with a
proxy, a pool on Redis shared by every server. See [Secret scanning](secret-scanning.md).

## The scopes

The scopes are configuration. An application reads them from `Phil\ApiKeysBundle\Application\ScopeCatalogue`, a public
service — to document its API, to build its own form —; `select()` checks a list against them.

## The members' page

The template is configuration, and the partial `@PhilApiKeys/_tokens.html.twig` goes into the application's layout:
see [The members' page](members-page.md). Twig's usual overriding works too, with
`templates/bundles/PhilApiKeysBundle/_tokens.html.twig`.

## What the application relies on

| Service | Role |
|---|---|
| `Phil\ApiKeysBundle\Application\ApiTokens` | Public: create, find, get, revoke, revoke all of an owner, rotate, purge and verify tokens |
| `Phil\ApiKeysBundle\Application\ScopeCatalogue` | Public: the declared scopes |
| `Phil\ApiKeysBundle\Application\OwnerTypes` | Public: the owner types and the scopes each may hold |
| `phil_api_keys.owner_providers` | The user providers of the owner types, by type, that the token handler loads owners with |
| `Phil\ApiKeysBundle\Domain\Contract\ApiTokenRepository` | Public alias of the store |
| `phil_api_keys.token_handler` | The token handler a firewall names in `access_token.token_handler` |
| `phil_api_keys.token_extractor` | The extractor of the configured header, named in `access_token.token_extractors` |
| `phil_api_keys.problem_responder` | The `401` as problem details, named as `entry_point` and `access_token.failure_handler`, when `phil/api-contract-bundle` is installed |
| `phil_api_keys.rate_limit_key` | The key strategy of `phil/rate-limit-bundle` by token, when it is installed |

The request attributes `_api_token_id` and `_api_token_scopes`, the security token attributes `_api_token_id` and
`oauth2_scope`, and the roles `ROLE_API_SCOPE_*` are part of the bundle's contract.
