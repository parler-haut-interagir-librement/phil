# Accounts and passwords

## Accounts

An account, a row of `phil_identity_account`, holds its identifier, its sign-in address in lower case, the canonical
form of that address and when it was verified, the name it shows, the Argon2id hash of its password — none for an
account that signs in with passkeys or a federated provider only —, a phone number and when it was verified, its
roles, the failed sign-ins in a row and the lock they lead to, and its state and when it last changed. A profile
beyond that belongs to the application.

Symfony Security sees it as `AccountUser`, identified by the account's identifier — never its address, which may
change and should not travel in links. `AccountUserProvider` loads it by that identifier or by the address typed in a
sign-in form, told apart by the `@` an address has and an identifier never has, and writes back a hash the password
hasher asks to strengthen. Two users are the same while their identifier,
roles, state and password hash are: a session signed in before the password changed or was removed is signed out at
its next request. The session the change is made in holds the new hash and stays; the rows of the other sessions, and
the tokens issued in them, are closed too (see [Devices and sessions](sessions.md)).

`AccountUserChecker` refuses an account that is not active, by any method, with `account.disabled`, and, with
`require_verified_email`, an account whose address is not verified, with `account.unverified`.

### Identifiers

The identifier of a new account comes from the port `Application\Contract\AccountIdGenerator`, named by
`accounts.id_generator`:

| Value | Identifier |
|---|---|
| `ulid`, the default | A ULID of 26 characters in Crockford's base32 (`UlidAccountIdGenerator`) |
| `uuid_v7` | A UUID of version 7 in its 36 characters (`UuidV7AccountIdGenerator`), ordered by time, as the keys of an application that uses UUIDv7 elsewhere |
| A service id | A service implementing `AccountIdGenerator` |

An identifier holds 1 to 36 letters, digits and hyphens (`Account::ID_PATTERN`) and is never given twice; every table
refers to it in a column of 36 characters. It stays inside the provider: what clients see as `sub` is the subject
strategy's, `oidc.subject` (see [Tokens](oidc-provider.md#tokens)). A UUIDv7 or a ULID tells when the account was
created; with either, give clients an opaque subject — `public` or `pairwise` —, random to them and separate from the
identifier, rather than `account`.

### States

`Domain\Account\AccountState` names the state of an account, generic enough for any application; only an active
account signs in:

| State | Meaning |
|---|---|
| `active` | The account signs in. |
| `pending` | The account waits for a decision of the application, such as an approval. |
| `suspended` | The account is set aside for a while, by a moderator or after a doubt, and may come back. |
| `closed` | The account is closed, by its owner or by the application, and waits for its removal. |

`Accounts::changeState()` moves an account to a state, journals `identity.account.state_changed` with `from` and `to`,
and, when the account leaves `active`, closes every session of the account and the tokens issued in them.
`Account::state()` and `stateChangedAt()` read it; `isDisabled()` is true in any state but `active`, `disable()`
suspends an active account and `enable()` makes it active again.

### Addresses

The sign-in address has a canonical form, `email_canonical`, unique among the accounts: two addresses of one canonical
form reach one mailbox, so one account is enough. Registration with another spelling of a known mailbox answers as
for the known address, and a change of address to it is refused with `email.taken`. The port
`Application\Contract\AddressCanonicalizer` gives the form; the bundle's `EmailCanonicalizer` keeps the address as
normalized and, with `accounts.strip_subaddress: true`, leaves out the subaddress — `ana+news@example.com` is
`ana@example.com`. Rules of one mail provider, such as the dots some ignore, belong to a service of the application
named in `accounts.canonicalizer`. The form of a stored address never changes on its own: a new rule needs the
stored forms computed again.

An account may also have an address for each of two other purposes, `Domain\Account\AddressPurpose`: `recovery`, to
get the account back, and `notices`, to receive its notices. They are rows of one table, `phil_identity_address`, each
with its canonical form, which belongs to one account for each purpose. `AccountAddresses` manages them:

| Method | What it does |
|---|---|
| `set($account, $purpose, $address)` | Sets the address of the purpose, unverified; refuses `sign_in` (`address.sign_in`), an address out of shape (`email.invalid`) or one another account holds for the purpose (`email.taken`). |
| `verify($account, $purpose)` | Marks it verified, after a link the application sent there for instance. |
| `addressFor($account, $purpose)` | Where to write for the purpose: its verified address, else the sign-in address. |
| `of($account)`, `get($account, $purpose)`, `remove($account, $purpose)` | Reads and removes them. |

The events `identity.address.set`, `identity.address.verified` and `identity.address.removed` carry the purpose, never
the address. The emails of the bundle go to the sign-in address; an `AccountMailer` of the application may write to
`addressFor()`.

## Registration

`/register` takes the address, a name, and the password when the module `password` is on. An address that already
has an account creates nothing and the page answers the same — "check your email" —, so that the form tells nobody
who has an account; the event `identity.account.registration_for_known_email` lets the application warn the owner.
A new account receives a link that verifies its address.

### Limiting the registrations

Each registration costs a password hash, a row and an email, so registration is limited by default, through the Rate
Limiter component:

```yaml
phil_identity_provider:
    registration:
        rate_limit:
            per_ip: { limit: 10, interval: '1 hour' }
            per_address: { limit: 5, interval: '1 hour' }
            resend_delay: '5 minutes'
```

`RegistrationRateLimiter` counts the registrations per client address — an IPv6 client by its /64 network — and per
address typed, known or not, so that the answer still tells nobody who has an account; a refusal answers `429` with
the form and `error.registration.too_many`. A verification email goes to one address once in each `resend_delay`,
whether it is asked for by registration, by the account page or by a change of address: one asked for again too soon
is not sent, and the page answers as if it were. The keys are keyed hashes, under a key derived from `secret` by HKDF-SHA256 for that limiter alone: the
cache never holds an address.

The counts live in `cache.app`, under `lock.factory` when it is defined. On several servers, point `cache.app` to a
shared store, or each server counts on its own. `registration: { rate_limit: false }` turns the limits off, for an
application that limits the route itself; `registration: false` closes registration.

## Verified addresses

Every link sent by email points to `public_origin`: see [Where the links point](#where-the-links-point).

The link of `/verify-email` carries the account's identifier, an expiry and a signature computed by the
`SignatureHasher` of Symfony Security, the one its login links use, over the address and the date it was verified,
under a key derived from `secret` for this purpose only. Opening it shows a page that asks to confirm; the address is
verified by the form that page posts, with its CSRF token. The scanners of mail services that open the links of a
message therefore verify nothing. The link stops working once used, once the address changes, or after
`links.verify_lifetime` seconds, with nothing stored — or, in the [stored mode](#stored-links), once its token is
consumed or revoked. A changed address is unverified until its own link is used.

## Stored links

By default the links are signed and nothing is stored: a link works until it expires or the properties it was signed
over change, and nobody can revoke one still out. `links.mode: stored` keeps a token for each link instead:

```yaml
phil_identity_provider:
    links: { mode: stored }
```

The link then carries a random token of 32 bytes in place of the signature; `phil_identity_link_token` keeps its
keyed hash — an HMAC under a key derived from `secret` —, the account, the purpose, the expiry, and the signature of
the properties as a fingerprint. A link is valid while its row exists, names the account and the purpose, has not
expired, and the account still has the properties it was signed over. Verifying an address consumes the token, once
even when two requests post it at the same time; a password reset revokes every reset link of the account. A copy of
the table opens no account.

`AccountLinks::revoke($account, $purpose)` revokes the links of an account still out — of one purpose, or all of them —
and journals `identity.links.revoked` with the count, as after a doubt about the mailbox. `phil:identity-provider:purge`
removes the expired tokens. The sign-in links of the module `login_link` are the login links of Symfony Security: give
the firewall's `login_link` its `max_uses` and a shared `used_link_cache` for them to work once.

## Passwords

A new password must hold 8 to 4096 characters — the bound keeps a hash from becoming a denial of service —, reach
`password.min_score` on the strength estimator of `phil/passwords`, the account's address and name counted as the
attacker's first guesses, and, with `password.breach_check`, be absent from Pwned Passwords, queried by k-anonymity. A
breach source that does not answer does not refuse the password. The reasons go back as translation keys:
`password.too_short`, `password.too_long`, `password.too_weak`, `password.breached`.

Passwords are hashed by the password hasher of Symfony Security: the bundle prepends `sodium` — Argon2id — for
`AccountUser`, and the application may tune it in `security.password_hashers`.

### The cap on password checks

Each Argon2id hash takes tens of mebibytes. A burst of failed sign-ins spread over many accounts and many addresses
passes under the limiters by address and by account, yet a server that hashed every one at once would run out of
memory. The bundle decorates the password hasher factory of Symfony Security: every hash and every check of an
account's password — sign-in, change, reset, registration — takes one of `password.semaphore.slots` locks of the Lock
component first, and waits up to `password.semaphore.wait` seconds for one to free. Past that wait the sign-in is
refused with `password.busy`, which never counts towards the lock of the account. Whether a hash needs computing
again takes no slot. The hashers of other user classes are left as they are.

The cap belongs to each server, since memory does: the default keeps flock files in the temporary directory, and a
lock dies with the process that held it. `password.semaphore.lock_factory` names another `LockFactory`, such as one on
`SemaphoreStore`; a store shared by every server would cap the whole site at the number of slots.

```yaml
phil_identity_provider:
    password:
        semaphore: { slots: 8, wait: 2.0 }
```

## The forgotten password

`/forgot-password` sends a link to the address when it has an account, and answers the same when it does not. The
link of `/reset-password` is signed over the password hash and the date of its last change: it stops working once the
password changes, or after `links.reset_lifetime` seconds. Following it and choosing a password also verifies the
address, which the link proved, and closes every session of the account.

The link is used once even when two requests present it at the same time: `Accounts::resetPassword()` sets the
password through `AccountRepository::apply()`, which reads the account again under a lock of its row, and only while
it still has the hash and the date the link was signed over. The request that comes second sees the link as used.

A password changed from the account page closes the other sessions of the account and keeps the one it was changed
in.

## The lock

`PasswordSignInListener` watches the password sign-ins of every authenticator of the firewall — `form_login`,
`json_login`, `http_basic`. Before the password is checked, a locked account is refused with `account.locked`, so that
guessing goes on against nothing; a wrong password counts towards the lock; a success clears the count. After
`lockout.threshold` failures in a row, each further failure locks the account for twice as long as the one before,
from `lockout.base` seconds up to `lockout.max`. The lock refuses the password only: a passkey, which a guessing
attacker cannot replay, still signs in. Each failure is counted on the account read again under a lock of its row, so
that failures at the same moment all count.

The lock lets anyone who knows an address lock its owner out by failing on purpose. `lockout: false` turns it off: the
failures are still counted and journaled as `identity.sign_in.failed`, and nothing is locked. Guessing is then left to
the [sign-in limiter](#limiting-the-sign-ins), which counts per address, per identifier and address, and per
identifier whatever the address, and refuses the attempts rather than the account: turn `login_limiter` on with it.

```yaml
phil_identity_provider:
    lockout: false
    login_limiter: { enabled: true }
```

## Limiting the sign-ins

The firewall's `login_throttling` limits the attempts in front of every authenticator. Its default limiter counts per
address and per identifier and address; an attack spread over many addresses stays under both. With `login_limiter`
enabled, the bundle offers a limiter that counts per identifier too, whatever the address:

```yaml
# config/packages/phil_identity_provider.yaml
phil_identity_provider:
    login_limiter:
        enabled: true
        account_max_attempts: 50   # per identifier, whatever the address
        cache_pool: cache.redis    # shared by every server

# config/packages/security.yaml
security:
    firewalls:
        main:
            login_throttling:
                limiter: phil_identity_provider.login_limiter
```

`LoginRateLimiter` counts three things in each `login_limiter.interval`: the attempts per address — an IPv6 client by
its /64 network —, `network_max_attempts`; per identifier and address, `max_attempts`; per identifier, whatever the
address, `account_max_attempts`. The keys are keyed hashes of the address and of the identifier, in lower case, under a key derived from
`secret` by HKDF-SHA256 for that limiter alone: the
cache never holds either. A successful sign-in clears both counts of its identifier and gives its token back to its
address. The options of `login_throttling` itself, `max_attempts` and `interval`, are not read once a limiter is named.

Two settings matter:

- The count per identifier can be spent by anyone who knows an address: failing on purpose holds back the sign-ins of
  that account for the interval. Keep `account_max_attempts` well above `max_attempts`; passkeys and sign-in links go
  through the same firewall, so the application that wants them spared names their authenticators in another firewall
  or another limiter. The progressive lock, which refuses the password only, stays on top.
- The address is the one Symfony sees. Behind a proxy or a load balancer, set `framework.trusted_proxies` and
  `framework.trusted_headers` to the proxies only: otherwise every client shares the proxy's address and its limit, or,
  with a header trusted from anyone, a client chooses its address and escapes it.

The counts live in `login_limiter.cache_pool`, `cache.rate_limiter` by default: on several servers, a pool on a shared
store — Redis, a database — counts for all of them, and `login_limiter.lock_factory`, on a shared lock store, makes two
attempts at the same moment both count. The bundle hands the pool to the `RateLimitGuard` of `phil/rate-limit`: in the
`prod` environment, a pool local to each server — the filesystem, APCu, an array — fails the container build, unless
`login_limiter.single_server: true` says the application runs on one server.

## Where the links point

The links of the emails — verification, reset, sign-in links — are absolute addresses, and a request names its own
host in its `Host` header. Without care, someone asks a reset link for another person while naming their own host:
the person receives a genuine, signed link that leads to that host, and one click hands over the token. The signature
covers the account and the expiry, not where the link points.

The bundle therefore builds those links on `public_origin`, the canonical address of the site:

```yaml
phil_identity_provider:
    public_origin: '%env(APP_PUBLIC_ORIGIN)%'   # https://example.com
```

The router's context names that origin — scheme, host and port — while the link is generated, and the request's
context comes back at once; the base path of the application is kept. The origin is read by `phil/url` when the
kernel boots: an `http` or `https` address with a host name, no IP address, no path, query or fragment; `localhost`
only in debug mode. An application that serves one host per tenant replaces `Application\Contract\LinkOrigin` with
its own, which knows the tenant's host.

Without `public_origin`, the links take the host of the request, which `framework.trusted_hosts` must bound:

```yaml
framework:
    trusted_hosts: ['^example\.com$']
```

A request for another host is then refused with `400` before any link is made. In the `prod` environment the bundle
refuses to boot when neither `public_origin` nor `framework.trusted_hosts` is set. Setting both is the safest: the
trusted hosts refuse forged requests to every page, the origin fixes where the emails point.

## The account page

`/account` shows the name, the address and whether it is verified, the forms that change them and the password, and
links to the pages of the modules turned on. Changing the address or the password needs a recent proof of the
account's strongest method: see [Step-up](step-up.md).
