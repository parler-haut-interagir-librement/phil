# Authentication per tenant

Three things can differ from one tenant to the next: whether a session signed in on one tenant is signed in on another,
which firewall signs an account in, and where the accounts come from. Each is off until configured.

## Sessions scoped to their tenant

When several tenants share one session cookie — several hosts under a common cookie domain, or several tenants on
one host — an account signed in on one tenant would be signed in on all of them. The bundle scopes the sessions of
the user providers it is given: an account is signed in on the tenant it signed in on, and on no other, unless the
application declares the account shared.

### Enabling it

```yaml
# config/packages/phil_tenancy.yaml
phil_tenancy:
    hosts:
        rose.example: rose
        tavern.example: tavern
    authentication:
        user_providers:
            - security.user.provider.concrete.members # the provider "members" of security.yaml
        shared_tenants:
            - [fair, fair-backstage]
```

`user_providers` lists the service ids of the providers to scope; SecurityBundle names the provider `members` of
`security.providers` `security.user.provider.concrete.members`. Scoping needs `symfony/security-http`.

### How it works

| When | What happens |
|---|---|
| A login succeeds (`LoginSuccessEvent`) | `RememberSignInTenant` writes the tenant of the login request in the application's session, under `_phil_tenancy.signed_in_on`; a login in no tenant removes it |
| A logout (`LogoutEvent`) | The tenant is removed, even when the firewall keeps the session |
| The firewall refreshes the account of a session | `TenantScopedUserProvider` lets the application's provider refresh the account, then refuses it when the request acts in another tenant than the one the session signed in on, unless `AccountSharing` shares it |

The refusal is a `UserNotFoundException`, the answer of a provider whose account no longer exists: the firewall's
context listener drops the token **before** `access_control` and the authenticators run. The request carries on as
anonymous, and a protected page asks for a login on this tenant. The firewall also removes the token from the shared
session, so the account must sign in again on the first tenant too.

The tenant of the request must be known while the firewall runs: it is when the request's host serves it
([hosts-and-shops.md](hosts-and-shops.md)). The tenant that `ResolveActiveTenant` finds from the account's choice is
resolved after the firewall: such an account reaches several tenants by design, and its session is not scoped.

A login on a host of no tenant — a central domain whose cookie the hosts share — opens a session in no tenant, and
such a session is signed in there only: on the host of a tenant, the account is refused like any other, unless the
application opens it explicitly:

- `shared_sessions: true` signs a session opened in no tenant in on every tenant, for a central back office that
  manages them all;
- with `reachable_tenants` and the request listener, the provider lets the session through and the reachable tenants
  decide after the firewall: a provider that answers every tenant opens them all, and `ResolveActiveTenant` refuses
  with a `403` a tenant the account cannot reach ([integrations.md](integrations.md));
- an `AccountSharing` of the application answers `isShared($account, null, $visited)`.

| Session | Request | Signed in? |
|---|---|---|
| Signed in on `rose` | on `rose` | Yes |
| Signed in on `rose` | on `tavern` | No, unless shared |
| Signed in on `rose` | in no tenant | No |
| Signed in outside any tenant | in no tenant | Yes |
| Signed in outside any tenant | on any tenant | No, unless `shared_sessions` or the application's `AccountSharing` shares it; with `reachable_tenants` and the request listener, on a tenant the account can reach, a `403` elsewhere |
| A remembered value that is no longer a valid reference | on any tenant | No |
| A stateless firewall | — | Not scoped: its authenticator reads `TenantContext` itself |

Which accounts may sign in on a tenant at all stays the application's decision. Its user provider or its
authenticator can read `TenantContext`, which already holds the host's tenant at login.

### Shared accounts

`Application\Contract\AccountSharing::isShared(string $account, ?TenantId $signedInOn, ?TenantId $visited)` is asked
only when the two tenants differ; `$account` is the identifier the user provider gives the account, which names a
member by a stable identifier — a UUID, an account number — and never by an email address, which a member may change
and which would then share or refuse the wrong account; `$signedInOn` is `null` for a session opened in no tenant,
and the two are never both `null`. The bundle's `ConfiguredAccountSharing` shares an account:

- between the tenants of a group of `shared_tenants`;
- between shops linked by the shop network: a general shop and the shops it includes, or two shops included by the
  same one, share their customer accounts;
- from no tenant to every tenant, with `shared_sessions: true`.

It treats every account of a tenant alike, and never shares a session opened on a tenant with a request in no tenant.
An application that shares some accounts only, such as staff accounts across every tenant, implements the port and
names it:

```yaml
phil_tenancy:
    authentication:
        user_providers: [security.user.provider.concrete.members]
        account_sharing: App\Security\StaffAccountsEverywhere
```

```php
final readonly class StaffAccountsEverywhere implements AccountSharing
{
    public function __construct(private StaffDirectory $staff) {}

    public function isShared(string $account, ?TenantId $signedInOn, ?TenantId $visited): bool
    {
        return $this->staff->isStaff($account);
    }
}
```

## A firewall for each tenant

A tenant, or a group of tenants, can sign its accounts in with a firewall of its own: its own authenticators, its own
login page, its own session context, so that a session of one firewall is never read by another. Each name of
`tenant_firewalls` registers a request matcher, `phil_tenancy.request_matcher.<name>`, that matches the requests in its
tenants; SecurityBundle's `request_matcher` puts it in front of a firewall:

```yaml
# config/packages/phil_tenancy.yaml
phil_tenancy:
    hosts:
        club-a.example: club-a
        club-b.example: club-b
    authentication:
        tenant_firewalls:
            club_b: [club-b]

# config/packages/security.yaml
security:
    firewalls:
        club_b:
            request_matcher: phil_tenancy.request_matcher.club_b
            provider: club_b_members
            form_login: { login_path: club_b_login, check_path: club_b_login }
        main:
            provider: members
            form_login: ~
```

SecurityBundle reads its firewalls in order: put the firewalls of tenants before the one that matches every request. The
matcher reads the tenant the request's host serves, entered before the firewall map is read; a request in no tenant, or
whose tenant is resolved later from the account's choice, matches no firewall of tenants. A list of no tenant stops the
compilation.

## A user provider for each tenant

The accounts of each tenant can come from its own provider — its own table, its own directory, its own identity
provider —, and those of the other tenants from a default provider. Each name of `tenant_user_providers` registers
`phil_tenancy.user_provider.<name>`, a `TenantUserProviders`, which SecurityBundle reads as a provider `id`:

```yaml
# config/packages/phil_tenancy.yaml
phil_tenancy:
    authentication:
        tenant_user_providers:
            members:
                tenants:
                    club-a: security.user.provider.concrete.club_a_members
                    club-b: security.user.provider.concrete.club_b_members
                default: security.user.provider.concrete.staff

# config/packages/security.yaml
security:
    providers:
        club_a_members: { entity: { class: App\Entity\ClubAMember, property: memberNumber } }
        club_b_members: { entity: { class: App\Entity\ClubBMember, property: memberNumber } }
        staff: { entity: { class: App\Entity\Staff, property: staffNumber } }
        members: { id: phil_tenancy.user_provider.members }
    firewalls:
        main:
            provider: members
```

| Request | Provider |
|---|---|
| In a tenant listed under `tenants` | That tenant's provider |
| In another tenant, or in no tenant | `default` |
| In another tenant, or in no tenant, without `default` | None: every account is unknown |

| Method | What it does |
|---|---|
| `loadUserByIdentifier()` | Loads the account from the provider of the request's tenant |
| `refreshUser()` | Refreshes the account through that provider; an account of a class it does not handle is unknown, so the firewall drops the token rather than hand it to another tenant's accounts |
| `supportsClass()` | Whether any of the providers handles the class |
| `upgradePassword()` | Passes the new hash to the provider of the request's tenant, when it upgrades passwords |

An unknown account is a `UserNotFoundException`, the answer of a provider whose account does not exist. The tenant is
the one of the request when the firewall runs, which the request's host gives. The providers of the tenants are built
only when a request asks for one. A user provider that `user_providers` scopes can be one of them, or this one.

Name each member by a stable identifier — the `property` of an entity provider, the identifier of an identity
provider —, never by an email address: the identifier is what the session remembers and what `AccountSharing` reads.

## Long-running servers

None of these services keeps anything between requests: the tenant of the session is read from the session, and the
tenant of the request from `TenantContext`, which is left between requests.
