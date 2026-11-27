# ASVS 5.0: what the application keeps

[Français](ASVS.fr.md)

The packages of this monorepo meet some requirements of [OWASP ASVS 5.0.0](https://github.com/OWASP/ASVS/tree/v5.0.0/5.0/en)
and show each one by a test; each package that does so has a page `doc/asvs.md`. Some requirements cannot be met by a
package: they depend on what the application records, on its accounts, its database roles or its hosts. This guide
gathers them, so that an application built on the packages knows what is still its own.

| Package | Its mapping |
|---|---|
| `phil/browser-security-bundle` | [doc/asvs.md](packages/browser-security-bundle/doc/asvs.md), chapter V3 |
| `phil/audit-bundle` | [doc/asvs.md](packages/audit-bundle/doc/asvs.md), chapter V16 |
| `phil/upload-scan` | [doc/asvs.md](packages/upload-scan/doc/asvs.md), chapter V5 |
| `phil/upload-scan-bundle` | [doc/asvs.md](packages/upload-scan-bundle/doc/asvs.md), chapter V5 |
| `phil/passwords` | [doc/asvs.md](packages/passwords/doc/asvs.md), chapter V6 |
| `phil/tenancy-bundle` | [below](#tenancy), chapter V8 |

## Documentation the application writes

| Requirement | Level | What to write |
|---|---|---|
| 8.1.1 | 1 | The rules of access to functions and to data: which role or voter guards each route and each kind of record. `phil/route-access-check` lists the routes and their rules. |
| 8.1.2 | 2 | The rules of access to fields, read and written: which fields a form or an API shows and accepts for each role. |
| 16.1.1 | 2 | The inventory of what is logged at each layer, where it is stored, for how long and who reads it: the segments of the audit trail, the application's log channels, the error tracker. |
| 3.1.1 | 3 | The browser features the application expects: HTTPS, HSTS, the content security policy. |
| 5.1.1 | 2 | The types and sizes of files each upload accepts. |

## Events the application records

The audit trail records what it is given. These events are the application's to send:

| Requirement | Level | Events |
|---|---|---|
| 16.3.1 | 2 | Every sign-in, successful or not, with the kind of authentication. Symfony's `LoginSuccessEvent` and `LoginFailureEvent` are where to listen. |
| 16.3.2 | 2 | Every refused authorization; at L3, every decision on sensitive data. Symfony's `AccessDeniedException` and the voters are where to listen. |
| 16.3.3 | 2 | Attempts to get around a control: a validation refused on purpose, a rate limit reached (`RateLimitExceeded`), a forged token. |
| 16.3.4 | 2 | Unexpected errors and failures of a control, such as a TLS failure towards a back end. |
| 16.2.1 | 2 | Where each event happened: the host, the route or the process, in the payload of the row. |

## Configuration and deployment

| Requirement | Level | What to set |
|---|---|---|
| 13.2.2 | 2 | A database role of least privilege: no `BYPASSRLS` when row-level security guards the tenants, no `UPDATE` nor `DELETE` on the audit table. |
| 8.4.1 | 2 | `framework.trusted_hosts`, whenever hosts choose the tenant: without it, a forged `Host` header chooses it. |
| 3.3.1 to 3.3.4 | 1, 2 | `Secure`, `HttpOnly` and `SameSite` session cookies, and the `__Host-` prefix where the cookie is not shared. |
| 3.4.2 | 1 | A fixed, or allowlisted, `Access-Control-Allow-Origin`. |
| 5.2.3 | 2 | ClamAV 1.5.4 or later, with `AlertExceedsMax yes` ([clamd](packages/upload-scan/doc/clamd.md#archives-and-their-limits)). |
| 6.2.1 | 1 | A `Length` constraint of at least 8 characters, 15 recommended, beside `MinimumStrength`. |
| 6.2.4 | 1 | `NotCompromised` on registration and password change: the offline dictionary of `phil/passwords` holds 314 passwords, the breach source the 3 000 most common and more. |

## Mapped arguments and votes

Since Symfony 8.2, an argument mapped from the request by `#[MapRequestPayload]`, `#[MapQueryString]` or
`#[MapUploadedFile]`, when it is the subject of an `#[IsGranted]`, is read and validated before the vote: a visitor the
vote would refuse reads the validation errors, and runs the constraints, an antivirus scan included. Write an
`#[IsGranted('IS_AUTHENTICATED')]`, or a role, before such a vote, or guard the path in `access_control`.
`phil/route-access-check` reports a route that does not as a conflict.

## Tenancy

`phil/tenancy-bundle` meets these requirements, each shown by its tests:

| Requirement | Level | How | Shown by |
|---|---|---|---|
| 8.4.1 No operation reaches another tenant | 2 | Reads: the Doctrine filter, fail-closed; the identity map detached when the tenant changes; the cache, Messenger and the user providers scoped to the tenant. Writes: the write guard refuses a flush of another tenant's entity | `IdentityMapTest`, `DoctrineFilterTest`, `CacheTest`, `MessengerTest`, `MembersTest` |
| 8.2.2 Access to data restricted to explicit permissions | 1 | Within a tenant: an account reaches only the tenants it may reach | `ActiveTenantRequestTest`, `SitesTest` |
| 8.3.1 Rules enforced on the server | 1 | The tenant is resolved by the server from the host, the session or the stamp, never from a field the client sets | `ActiveTenantRequestTest` |
| 13.2.2 Least privilege towards the database | 2 | Optional row-level security on PostgreSQL, below the ORM | `PostgreSqlRowLevelSecurityTest` |

Left to the application: writes through DBAL or native SQL, which neither the filter nor the write guard sees — row-level
security covers them —; the tenant reference of each new entity, set from the resolved tenant; `trusted_hosts`; and a
flush before switching tenants, since a change to a loaded entity is not scheduled before it.
