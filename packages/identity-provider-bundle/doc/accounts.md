# Accounts and passwords

## Accounts

An account, a row of `phil_identity_account`, holds a ULID, an address in lower case and when it was verified, the
name it shows, the Argon2id hash of its password — none for an account that signs in with passkeys or a federated
provider only —, a phone number and when it was verified, its roles, the failed sign-ins in a row and the lock they
lead to, and when it was disabled. A profile beyond that belongs to the application.

Symfony Security sees it as `AccountUser`, identified by the account's ULID — never its address, which may change and
should not travel in links. `AccountUserProvider` loads it by that identifier or by the address typed in a sign-in
form, and writes back a hash the password hasher asks to strengthen. Two users are the same while their identifier,
roles and state are: a password change does not end the session it was made in; the other sessions are closed by
their rows (see [Devices and sessions](sessions.md)).

`AccountUserChecker` refuses a disabled account, by any method, with `account.disabled`, and, with
`require_verified_email`, an account whose address is not verified, with `account.unverified`.

## Registration

`/register` takes the address, a name, and the password when the module `password` is on. An address that already
has an account creates nothing and the page answers the same — "check your email" —, so that the form tells nobody
who has an account; the event `identity.account.registration_for_known_email` lets the application warn the owner.
A new account receives a link that verifies its address.

## Verified addresses

The link of `/verify-email` carries the account's identifier, an expiry and a signature computed by the
`SignatureHasher` of Symfony Security, the one its login links use, over the address and the date it was verified,
under a key derived from `secret` for this purpose only. It stops working once followed, once the address changes, or
after `links.verify_lifetime` seconds, with nothing stored. A changed address is unverified until its own link is
followed.

## Passwords

A new password must hold 8 to 4096 characters — the bound keeps a hash from becoming a denial of service —, reach
`password.min_score` on the strength estimator of `phil/passwords`, the account's address and name counted as the
attacker's first guesses, and, with `password.breach_check`, be absent from Pwned Passwords, queried by k-anonymity. A
breach source that does not answer does not refuse the password. The reasons go back as translation keys:
`password.too_short`, `password.too_long`, `password.too_weak`, `password.breached`.

Passwords are hashed by the password hasher of Symfony Security: the bundle prepends `sodium` — Argon2id — for
`AccountUser`, and the application may tune it in `security.password_hashers`.

## The forgotten password

`/forgot-password` sends a link to the address when it has an account, and answers the same when it does not. The
link of `/reset-password` is signed over the password hash and the date of its last change: it stops working once the
password changes, or after `links.reset_lifetime` seconds. Following it and choosing a password also verifies the
address, which the link proved.

## The lock

`PasswordSignInListener` watches the password sign-ins of every authenticator of the firewall — `form_login`,
`json_login`, `http_basic`. Before the password is checked, a locked account is refused with `account.locked`, so that
guessing goes on against nothing; a wrong password counts towards the lock; a success clears the count. After
`lockout.threshold` failures in a row, each further failure locks the account for twice as long as the one before,
from `lockout.base` seconds up to `lockout.max`. The lock refuses the password only: a passkey, which a guessing
attacker cannot replay, still signs in. The firewall's `login_throttling` limits the attempts per network address in
front of it.

## The account page

`/account` shows the name, the address and whether it is verified, the forms that change them and the password, and
links to the pages of the modules turned on. Changing the address or the password needs a recent proof of the
account's strongest method: see [Step-up](step-up.md).
