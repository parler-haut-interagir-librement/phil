# Devices and sessions

The module `sessions` ties each PHP session to a row of `phil_identity_session`: an opaque identifier of 256 bits the
PHP session holds, the device — a browser and a system read coarsely from the user agent, such as "Firefox on Linux",
never the address of the connection —, the methods proven, whether the sign-in looked risky, and when it was opened,
last seen and closed.

## What SessionListener does

- A first sign-in opens a row; a later one in the same session — the second step, a re-authentication — adds its
  methods to it.
- Each request after the firewall sees the row, at most once every `sessions.touch_interval` seconds. A row closed
  from another device ends the PHP session there and then, and the request goes on signed out.
- Signing out closes the row.

## When a session ends on its own

A session closes after `sessions.idle_timeout` seconds without a request — 7200, two hours, by default —, and
`sessions.max_age` seconds after it opened, however active — 43200, twelve hours, by default:

```yaml
phil_identity_provider:
    sessions:
        idle_timeout: 7200   # 0 for no bound
        max_age: 43200       # 0 for no bound
```

`Sessions::touch()` checks both bounds before it records the request, and `Sessions::openFor()` before it lists the
sessions of an account. A session past either bound is closed as from another device: its tokens are revoked, the
row closed, the event `identity.session.closed` written with the reason, `idle_timeout` or `max_age`, and the PHP
session that holds it ends at its request, which goes on signed out. Signing in again opens a new session.

The last request is written at most once every `sessions.touch_interval` seconds, so an idle session may close up
to that many seconds before `sessions.idle_timeout`; the idle timeout is longer than the interval, and the bundle
refuses a configuration where it is not. The PHP session itself lives by the settings of the framework
(`framework.session.gc_maxlifetime`, `cookie_lifetime`); the bounds of the bundle hold whatever those are.

A session past a bound and never touched again by its browser is closed when the account lists its sessions, or when
a client of the OpenID Connect provider refreshes a token issued in it. A refresh token bound to a session touches it,
as a request does:

- past `sessions.idle_timeout` or `sessions.max_age`, the session is closed there and then, with every token issued
  in it, and the refresh is refused with `invalid_grant`; the next request of its browser goes on signed out;
- within them, the refresh counts as the activity of the session: a person who keeps using an application signed in
  through the provider keeps the session open without coming back to the provider, up to `sessions.max_age`, which no
  activity extends;
- no refresh token issued in a session expires after `sessions.max_age`, nor after `oidc.session_max_age` when it is
  set.

A refresh token of a closed session is refused, and its family revoked (see
[OpenID Connect provider](oidc-provider.md#refresh-tokens-and-the-session)).

A request without a previous session reads no token: the lazy firewall stays lazy, and the cache of a public page
stays public.

## The page

`/account/sessions` lists the open sessions of the account with their device and when they were opened and last seen,
the current one marked with `aria-current`. One is closed from there; all the others at once after a recent proof.
Closing a session revokes the tokens the OpenID Connect provider issued in it, then closes the row, in one transaction
of the `Transactions` contract — `DoctrineTransactions` on the entity manager of the stores. Without one, a failure
leaves the row open, so that closing it again finishes the work. A new password closes every other session of the
account, a reset password all of them (see [Accounts and passwords](accounts.md)). With the provider on, the page also
lists the applications the account allowed, whose consent is withdrawn from there, revoking their tokens.

## Risky sign-ins

A sign-in from a device the account never used, when it has signed in before, is risky: the session is marked, the
event `identity.sign_in.risky` is written, and the account receives an email naming the device and the time, with what
to do if it was not them.

## Purge

`phil:identity-provider:purge --session-days=30` removes the closed sessions and those idle for longer than the days
given.
