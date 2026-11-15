# Installation

## Requirements

- PHP 8.5 or later, with the JSON, mbstring, OpenSSL, sodium and URI extensions — OpenSSL for the RSA signatures that
  `web-token/jwt-library` makes and checks;
- `web-token/jwt-library`, which signs and verifies the tokens;
- `phil/url`, which reads every address, and `phil/safe-http`, which reads the key sets of the other side and sends the
  requests of the services of LTI Advantage;
- Symfony's cache contracts, for the key sets and the access tokens kept between requests;
- a PSR-20 clock, such as Symfony's Clock component.

## Installing

```sh
composer require phil/lti
```

In a Symfony application, install `phil/lti-bundle` instead: it registers the services, keeps the stores in tables,
mounts the routes and turns a launch into a security token.

## What the application provides

The library holds no state of its own. The application gives it:

| What | Why | Port |
|---|---|---|
| The platforms the tool is registered with, and their deployments | A launch is accepted only from them | `PlatformRegistrations` |
| The tools the platform has registered, and their deployments | A launch is sent only to them | `ToolRegistrations` |
| A store of login states | The `state` and the `nonce` of a login live until its launch | `LoginStates` |
| A store of values used once | A replayed `nonce` or `jti` is refused | `Nonces` |
| Its keys | It signs with the current one and publishes its key set | `KeyStore` |
| A cache | Key sets and access tokens are not read at every request | Symfony `CacheInterface` |
| On the platform: access tokens, line items, scores, a roster | The services of LTI Advantage | `AccessTokens`, `LineItemStore`, `ScoreStore`, `Roster` |

Each port ships with an adapter in memory, for tests and for a single process; see
[extension points](extension-points.md).
