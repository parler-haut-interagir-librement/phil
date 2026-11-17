# Authenticator application and recovery codes

## The authenticator application

The module `totp` lets an account add an authenticator application, which computes time-based one-time passwords
(RFC 6238): HMAC-SHA-1 of the number of 30-second periods since the epoch, six digits.

`/account/two-factor`, after a recent proof, generates a secret of 160 bits and shows its `otpauth://` address — the
link an application on the same device opens, the content of a QR code — and the secret in base32 for typing by hand.
The secret is kept sealed by the `SecretCipher`: encrypted by the KeyManagement component under `secrets.kms_key`, or,
with `secrets.allow_plaintext_secrets: true` only, in clear. It counts for nothing until a first code confirms it. A
code is accepted within `totp.window` periods before and after the current one, and once: the counter it matched is
kept, and the same code, or an older one, never verifies again. Every counter of the window is computed, so that the
time taken says nothing of which one matched. The counter is read again, and held, before it is saved: of two requests
presenting the same code at the same time, only one is accepted.

## Recovery codes

With the module `recovery_codes`, confirming the application generates ten codes of ten characters from an alphabet
without look-alikes, shown once as `XXXXX-XXXXX`. They are kept as HMAC-SHA-256 under a key derived from `secret`; a
code is read without case, spaces or dashes, compared in constant time, and used once — once even by two requests at
the same time, the codes being read again and held before one is crossed out. New codes replace the old ones.
Turning the application off removes them.

## The second step

When an account has a confirmed authenticator application, `SecondFactorListener` sends every request to
`/two-factor` until the token holds a strong proof — `otp`, `hwk` or `mfa` —, whatever the first method was: a password,
a sign-in link, a federated provider. The challenge page offers a code of the application, a recovery code and a
passkey of the account. `OneTimeCodeAuthenticator` takes the code, authenticates the same account again with the proof
`otp`, which Symfony Security 8.2 adds to the proofs the token already holds, and sends the person where they were
going. Only routes named exactly stay reachable — the challenge, the code it posts, the sign-in with a passkey, and
those of `open_routes` such as the logout route —, so that the person can sign out; no prefix opens a route, not even
the underscore of the profiler's, which an application lists in `open_routes` in development if it wants the toolbar.

The firewall's `login_throttling` applies to `OneTimeCodeAuthenticator` like to any authenticator.
