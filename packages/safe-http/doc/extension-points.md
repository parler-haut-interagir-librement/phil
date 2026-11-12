# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Read through another transport — a proxy, a scoped client, a recording of the tests | Pass it as `client`: the guard wraps it |
| Pin a host to an address, or make tests resolve no name | Pass `resolve`: the address is still checked against the private networks |
| Change what one read may cost | Pass other `Limits` |
| Read another part of a body | Pass another `ReadRule` |

What stays closed: the guard itself. `SafeHttpClient`, `AddressGuard` and the value objects are final; a site that
needs an address the guard refuses — a service of its own network — reads it with its own client, not this one.
