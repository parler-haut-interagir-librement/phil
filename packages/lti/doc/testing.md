# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | What it covers |
|---|---|
| `tests/Unit` | Tokens, keys and key sets; the claims of a launch read and written; the whole launch from the platform's initiation to the tool's verified launch, and each refusal on the way; Deep Linking from the tool's response to the platform's result; access tokens from the tool's assertion to the platform's guard; the clients of AGS and NRPS on recorded answers; the services of the platform; the stores in memory. |

The tests run without a network: the keys are generated on the first run and kept in the temporary directory, so that
no private key is committed; the key sets come from a fixture; the services answer from a recording client or
Symfony's `MockHttpClient`. In the monorepo, Infection holds the package at 100 % of covered mutants.

## Fuzz targets

| Target | What it reads |
|---|---|
| `lti-jwt` | Compact tokens and key sets. |
| `lti-launch-claims` | The claims of a launch, read and written back, and the content items of a deep linking response. |
| `lti-link-header` | `Link` headers. |

```sh
composer fuzz -- lti-jwt lti-launch-claims lti-link-header --budget=60
```

## The certification suite

1EdTech certifies tools and platforms of LTI Advantage through its certification suite, open to its members. Running
it against an application built on this package is a step before the first stable release: the suite launches the
tool from its test platform, or acts as a tool for the platform, and checks the messages, the services and the
refusals.

## Testing an application

Build the platform and the tool of a test from the adapters in memory, with a frozen clock and a key set fixture that
gives the other side's public keys: `LaunchIssuer` signs a launch the tool's `LaunchValidator` verifies, without a
browser or a network.
