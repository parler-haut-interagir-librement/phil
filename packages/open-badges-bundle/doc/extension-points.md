# Extension points

## `StatusLists`

The application's status lists, `Phil\OpenBadgesBundle\Application\Contract\StatusLists`:

```php
interface StatusLists
{
    public function find(string $list): ?StatusListContent;
}
```

`find()` answers the content of a list — the indexes whose bit is set and the purpose, `revocation` or `suspension` —,
or null for a list the application does not have. It is called for each status list served and for each credential
the verification page checks against one of the site's lists: an implementation reads an index kept with what was
issued, not a scan of every credential. See [Publishing](publishing.md).

## The layout of the verification page

`verification.layout` names the Twig template the page extends, with the blocks `title` and `content`; the page itself
is overridden like any bundle template. See [Verification page](verification-page.md).

## Trusted keys

`trusted_keys` maps the verification methods of other issuers to their Ed25519 keys, as `publicKeyMultibase`: the
verifier and the page accept their credentials when the method is under the issuer's identifier, such as
`https://partner.example/issuer#key-1` for `https://partner.example/issuer`. To verify issuers the application does
not list, `remote_issuers` reads their profiles ([Other issuers](remote-issuers.md)).

## The verification methods

`Phil\OpenBadges\Contract\VerificationMethods` is the service the verifier asks for keys: the known keys,
`phil_open_badges.known_methods`, or `RemoteVerificationMethods` around them when `remote_issuers` is enabled. `did:web`
issuers are already read there. An application that finds keys elsewhere — a registry of its partners, another DID
method — decorates it; a decorator
asks the inner service first and throws `InvalidCredential` for a method it does not hold. The HTTP client of the
profiles, `phil_open_badges.safe_http_client`, is a `SafeHttpClient` over the application's `http_client` when there is
one: a scoped client or a recording of the tests goes there, and the guard still wraps it.

## The JSON-LD contexts

`contexts` gives the Data Integrity suite the context documents the application provides, a file for each URL. To
read them from elsewhere — a cache, a database —, replace the argument of the `Phil\OpenBadges\DataIntegrity\EddsaRdfc2022`
service with a `Phil\OpenBadges\Contract\ContextLoader` of the application's; a loader that fetches contexts lets a
document choose what its own terms mean, so it serves the contexts the application accepts and refuses the others.

## The limit on the checks

`verification.rate_limit` names any limiter of `phil/rate-limit-bundle`, its own or one another package tags
`phil_rate_limit.limiter`. A route of the application that serves the page under another name keeps its own
`_rate_limit` default: the limit applies to the bundle's route only.

## The library's services

Every service of `phil/open-badges` the bundle registers can be decorated or replaced by its class: a `Signer` over a
key management service, for instance, replaces `Phil\OpenBadges\Contract\Signer`.
