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

A request without a previous session reads no token: the lazy firewall stays lazy, and the cache of a public page
stays public.

## The page

`/account/sessions` lists the open sessions of the account with their device and when they were opened and last seen,
the current one marked with `aria-current`. One is closed from there; all the others at once after a recent proof.
Closing a session revokes the tokens the OpenID Connect provider issued in it. With the provider on, the page also lists
the applications the account allowed, whose consent is withdrawn from there, revoking their tokens.

## Risky sign-ins

A sign-in from a device the account never used, when it has signed in before, is risky: the session is marked, the
event `identity.sign_in.risky` is written, and the account receives an email naming the device and the time, with what
to do if it was not them.

## Purge

`phil:identity-provider:purge --session-days=30` removes the closed sessions and those idle for longer than the days
given.
