# The active tenant

An account of a multi-tenant application may be a member of several tenants: retail and wholesale, two
organisations, two shops. The **active tenant** is the one it acts in right now. This bundle decides how it is
resolved and switched; the application decides where it is remembered and where the reachable set comes from.

## The design

- **The active tenant belongs to the application, never to the identity provider.** The provider's session and
  its ID token span every application that shares the provider, while an active tenant belongs to one. The
  provider knows which tenants an account *can* act in; it does not know, and is never asked, which one it acts
  in right now. Nothing of the active tenant goes into a token, a provider session or a provider cookie.
- **Switching is checked against the reachable set and nothing else.** No round trip, no new token, no new login:
  the switch is instant and keeps working while the identity provider is down.
- **A switch to an unreachable tenant is refused** (`TenantNotReachable`), never narrowed to a reachable one.
- **Resolution never guesses.** With several reachable tenants and no usable choice, the account selects; the
  bundle never picks the first of a list. See [resolution.md](resolution.md).
- **Auditing what is done inside a tenant is the application's.** The identity provider can audit who was granted
  what; only the application's own logs can say what an account did inside a tenant.

## Tenant references: `TenantId`

`Phil\TenancyBundle\Domain\ValueObject\TenantId` is the reference of a tenant inside **one** application. The same
reference in two applications names two unrelated tenants, so it is only compared with references of the same
application.

| Member | Behaviour |
|---|---|
| `static fromString(string $value): self` | Validates and wraps the reference; throws `InvalidValue` |
| `public string $value` | The reference, exactly as given |
| `equals(TenantId $other): bool` | Byte-for-byte comparison |
| `toString()`, `__toString()` | The reference |
| `const MAX_LENGTH = 255` | Longest reference, in bytes |

The reference is opaque: a slug, a UUID, a number, as the application chooses. `fromString()` refuses only what
cannot be a stable key:

| Refused | Example |
|---|---|
| Empty | `''` |
| Longer than 255 **bytes** (not characters) | 128 times `é` is 256 bytes |
| Not valid UTF-8 | `"\xff\xfe"`, `"retail\xC3"` |
| Leading or trailing whitespace, Unicode spaces included | `' retail'`, `"retail\n"`, `"retail\u{00A0}"`, `"\u{3000}retail"` |
| A control character anywhere: C0, DEL, C1 | `"re\x00tail"`, `"re\ttail"`, `"re\u{0085}tail"` |

An inner space is part of the reference (`"north\u{00A0}shore"` is accepted). **Case is significant**: `retail` and
`Retail` are two tenants, because folding case would let a member of one act in another whose reference happens
to collide.

## The reachable set: `ReachableTenants`

`Phil\TenancyBundle\Domain\ValueObject\ReachableTenants` is the set of tenants the current account **can** act in,
as the application's entitlements say. It is `Countable` and iterable over `TenantId`, in the order given.

| Member | Behaviour |
|---|---|
| `static of(TenantId ...$tenants): self` | The set, in the given order; a duplicate throws `InvalidValue` rather than being collapsed |
| `static none(): self` | The empty set |
| `contains(TenantId $tenant): bool` | Membership, by `TenantId::equals()` |
| `isEmpty(): bool` | No tenant reachable |
| `single(): ?TenantId` | The tenant when exactly one is reachable, `null` otherwise |
| `toList(): list<TenantId>` | The tenants, as a list |
| `count(): int`, `getIterator()` | `Countable` and `IteratorAggregate` |

- **A duplicate is an error.** It means the entitlements were read wrong, and deduplicating silently would hide it.
- **An empty set is legitimate.** It is what stale entitlements resolve to when they fail closed, and it makes
  every tenant unreachable.

## The service: `ActiveTenant`

`Phil\TenancyBundle\Application\ActiveTenant` applies the rules to the two ports. The bundle registers it, public,
once `reachable_tenants` is configured (see [configuration.md](configuration.md)).

| Method | Behaviour |
|---|---|
| `__construct(ActiveTenantStore $store, ReachableTenantsProvider $provider)` | |
| `resolve(): Resolution` | Resolves from the remembered choice and the reachable set; calls `$store->forget()` when the remembered choice is no longer reachable |
| `canReach(TenantId $tenant): bool` | Whether the reachable set holds the tenant: what `ResolveActiveTenant` checks for a signed-in account on a host's tenant |
| `switchTo(TenantId $tenant): void` | Checks the tenant against the reachable set and remembers it; throws `TenantNotReachable` and leaves the remembered choice untouched when it is not reachable |
| `leave(): void` | Forgets the remembered choice |

## A request, end to end

1. The firewall authenticates the account (`kernel.request`).
2. `ResolveActiveTenant`, placed after the firewall, calls `ActiveTenant::resolve()`: the remembered choice from the
   store, the reachable set from the application's provider. A choice no longer reachable is forgotten.
3. On `Outcome::Active`, the tenant is entered in `TenantContext`, and `DoctrineTenantActivation` sets it on the
   filter: every query of the request sees that tenant's rows only.
4. On `Outcome::SelectionRequired`, the account is sent to the selector route, which lists `reachable_tenants()`
   and posts to the switch; the switch checks the tenant, remembers it and returns to the page first asked for.
5. On `Outcome::NoneReachable`, the account stays signed in and acts in no tenant: tenant-scoped entities match
   nothing.
6. The controller reads the tenant with `#[CurrentTenant]`, a message dispatched carries it in a `TenantStamp`,
   and the context is left before the next request.

Each step is in [integrations.md](integrations.md). An application that wants its own listener disables the
bundle's (`request: false`) and calls `ActiveTenant::resolve()` and `TenantContext::enter()` itself, after
authentication and before the first tenant-scoped query.

## Exceptions

| Exception | Kind | Thrown by | When |
|---|---|---|---|
| `Domain\Exception\TenantNotReachable` | `DomainException`, `DomainError` | `ActiveTenantRule::switchTo()`, `ActiveTenant::switchTo()` | The requested tenant is not in the reachable set |
| `Domain\Exception\NoActiveTenant` | `DomainException`, `DomainError` | `Resolution::tenant()`, `TenantContext::require()` | No tenant is active; its message says whether a selection is required, none is reachable, or the unit of work acts in no tenant |
| `InvalidValue` (from `phil/ddd-bundle`) | `InvalidArgumentException`, `DomainError` | `TenantId::fromString()`, `ReachableTenants::of()`, `new TenantScoped('')` | A malformed reference, a duplicate tenant, an empty field name |
