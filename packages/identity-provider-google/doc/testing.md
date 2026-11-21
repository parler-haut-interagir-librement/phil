# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network. In the monorepo, Infection holds
the package at 100 % of covered mutants.

## The local Google

`tests/Fixture/FakeGoogle` stands for Google, offline:

- its discovery document and the configuration document of Cross-Account Protection are fixtures shaped as Google
  publishes them, in `tests/Fixture`;
- its key set holds an RSA key made for the run, and `FakeGoogle::sign()` signs ID tokens and security event tokens
  with it, or with another key to test a forged signature;
- its token endpoint answers with the ID token a test chose, and every request is kept, with its headers and body;
- the host names are pinned to public addresses of the documentation range, so phil/safe-http resolves nothing and
  still applies its guard; the HTTP transport is Symfony's `MockHttpClient`.

`SignInHarness` builds `GoogleSignIn` as the bundle's services do — Symfony's discovery, client, signature verifier and
claim checks over the local Google —, with a session and a clock at a fixed time. `RiscHarness` builds the receiver over
phil/identity-provider-bundle's stores in memory: an account linked to the Google subject, with two open sessions.

## The fuzz target

`tests/Fuzz/identity-provider-google-claims.php` reads JSON nobody wrote as the claims of a security event token and as
the claims of a Google ID token. It accepts only a token within its bounds or `InvalidSecurityEventToken`, and an
identity whose subject is printable and whose address is trusted only on Google's authority, or `Refused`:

```sh
composer fuzz -- identity-provider-google-claims
```

Its seeds are in `tests/Fuzz/corpus/identity-provider-google-claims`.
