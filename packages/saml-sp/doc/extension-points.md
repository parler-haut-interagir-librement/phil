# Extension points

The library is in its `0.x` releases: the ports, the value objects and the exceptions may change in a minor release,
said in the changelog. They are meant to be stable from `1.0`.

## Ports

| Port | Implementations | Replace it to |
|---|---|---|
| `Contract\ReplayGuard` | `DbalReplayGuard` (Doctrine DBAL), `CacheReplayGuard` (PSR-6), `InMemoryReplayGuard` (tests) | keep accepted assertions in another store |
| `Contract\PendingRequests` | `CachePendingRequests` (PSR-6), `InMemoryPendingRequests` (tests) | keep pending requests elsewhere |
| `Contract\MetadataFetcher` | `SafeHttpMetadataFetcher` | fetch metadata through a proxy, or from a store |

A `ReplayGuard` answers `true` once per issuer and assertion ID until the expiry it is given, on every worker and server.
`DbalReplayGuard` decides with one atomic insert on the primary key of its table; `CacheReplayGuard` reads then writes,
so two copies of one assertion posted within the same instant to two servers could both pass. See
[The replay store](replay-store.md).

## Options

- `SecurityPolicy`: the clock skew, the age of a response, the `SignaturePolicy` (SHA-1, the smallest RSA key) and the
  `XmlBudget` of a message.
- `IdentityProvider`: the attribute map, `wantAssertionsSigned`, `wantAssertionsEncrypted`, `allowUnsolicited`, the
  binding of the requests.
- `ServiceProvider`: the entity ID, the services, the key pairs, the name identifier format, whether requests are signed.
- `AttributeMap`: any names per field; `with()` changes one.
- `MetadataReader`: the signature policy, the budget, `allowLocalhost`.
- `MetadataLoader`: the fetcher, the cache and its lifetime.
