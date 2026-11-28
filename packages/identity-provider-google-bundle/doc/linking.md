# Linking accounts

The package reads who Google vouches for; phil/identity-provider-bundle decides what to do with it. This page says
what the package hands over, and how the bundle's rules then apply.

## The sign-in

1. `/login/with/google` calls `GoogleProvider::start()`: the package reads Google's authorization endpoint from its
   discovery document, makes a state, a nonce and a PKCE verifier of 256 bits each, keeps them in the session with the
   callback address, and sends the person to Google with the state, the nonce, the PKCE challenge (S256), the scopes,
   `access_type=online` and the configured `prompt` and `hd`.
2. Google sends the person back to `/login/with/google/callback`, where the bundle's `ExternalAuthenticator` calls
   `GoogleProvider::finish()`. The package takes the attempt of the state — compared with `hash_equals()`, removed
   whatever comes of it —, refuses an answer that waited past `attempt_lifetime`, an error of Google, an answer
   without a code, and an `iss` parameter that is not Google's (RFC 9207).
3. The code is exchanged at Google's token endpoint with the PKCE verifier and the client secret. The ID token's
   signature is checked against Google's keys, then its issuer — `https://accounts.google.com`, or
   `accounts.google.com` as Google also writes it —, its audience, its `azp`, its times and its nonce.
4. The claims are mapped to the identity the bundle links (see below), and a subject held after a hijacking is
   refused.

The access token and the ID token are read and dropped: nothing of them is kept, and no refresh token is asked for.
Every refusal is the bundle's `external.failed`; its cause is logged at the `info` level, without a token or the
person's data.

Several sign-ins may wait at once, one per tab, five at most: the oldest is dropped when a sixth starts, so that a
visitor cannot grow the session.

## The identity handed over

| Field | From | Rule |
|---|---|---|
| `provider` | — | `google` |
| `subject` | `sub` | Google's stable identifier of the account, printable ASCII of 1 to 255 bytes; anything else is refused. The address is never the key. |
| `email` | `email` | The address when it is one, at most 254 bytes; none otherwise. |
| `emailVerified` | `email_verified`, `hd` | True only when Google is the address's authority: `email_verified` is true and the address is a `gmail.com` one, or the account belongs to a Google Workspace domain (`hd`) and the address is on that domain. |
| `displayName` | `name` | Only with `profile: true`: control characters removed, trimmed, 200 characters at most. |

A Google account may carry an address of any domain — a consumer account made with a work address, for example —,
and Google marks it verified once the person proved they read its mail. That proves less than an account of the domain
itself, so the package hands such an address over as unverified.

## The bundle's rules

`ExternalIdentities` of the bundle then decides, in order:

1. a Google account already linked signs its account in — and is refused when someone else is signed in;
2. someone signed in links the Google account to their own account;
3. a Google account whose address an account already has is refused with `external.email_known`: the person signs in
   to that account and links Google from it. Accounts are merged on the address only when the bundle's
   `external.link_verified_email` is on, the package handed the address over as verified, and the account verified it
   too;
4. otherwise, with the bundle's `external.register`, an account without a password is created and linked, its address
   verified when the package handed it over as verified.

## Linking and unlinking

A person signed in links Google from the bundle's page `/account/identities`, whose "Link with Google" button starts
the same flow; the identity is then linked to their account. The same page unlinks it, after a recent proof — never the
last way to sign in of an account without a password nor a passkey.

## Accepted domains

With `hosted_domains`, only Google Workspace accounts of those domains sign in: the `hd` claim, which Google sets on
Workspace accounts only, must be one of them. The `hd` parameter sent to Google only narrows its account chooser; a
person can remove it from the address, and the claim is what is checked.

## Asking for more

The package asks for the address only, and for the name with `profile: true`. An application that needs more of a
person's Google data — their calendar, their contacts — asks for it with its own consent screen and its own tokens, at
the moment it needs them: this package keeps no token to call Google's APIs with.
