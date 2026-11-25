# Changelog

All notable changes to `phil/api-keys-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-24

Instants are written and read in UTC, whatever the time zone of the process.

### Changed

- `doctrine/dbal` is required, no longer for development only: the Doctrine store names the types of its parameters.
- Requires the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6, `symfony/service-contracts`
  3.8), and of each development requirement (`doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31,
  `symfony/cache-contracts` 3.8, `symfony/dom-crawler` 8.2, …).

### Security

- The instants of `phil_api_token` — creation, expiry, last use, revocation — are written and read in UTC, whatever
  the time zone of the PHP process, as `datetime_utc_immutable`, and the last use and the purge pass their instants with
  the same type: a web server, a command and a worker in different time zones read the same instant, and an expired
  token can no longer be accepted for a few hours. The SQL type does not change; a database written outside UTC is
  converted once ([Upgrading from 0.1](doc/installation.md#upgrading-from-01)).

## 0.1.0-alpha - 2026-11-20

### Added

- `Domain\Token\TokenFormat`: tokens written `<prefix>_<id>_<secret><checksum>` — a configurable prefix for secret
  scanners, a 12-character identifier, a 32-character secret drawn without bias by `random_int()` from the secure source
  of `random_bytes()`, a 6-character CRC-32 checksum that refuses a mistyped token offline —, read back strictly by
  length, prefix, separators, characters and checksum; `PresentedToken`, which prints as its identifier and refuses
  serialization; `MalformedToken`, whose message never repeats the text; a fuzz target of the reader.
- `Domain\Token\TokenOwner`, who a token acts for: a type — `user` for a member, or another the configuration
  declares, such as `organization` or `application` — and an identifier.
- `Domain\Model\ApiToken`, the row of the table `phil_api_token`: owner type and owner, name, scopes, the keyed hash of the secret,
  creation, required expiry, last use with an anonymized address, revocation; `InvalidTokenValue`.
  `Domain\Contract\ApiTokenRepository`, with a Doctrine store — the last use and the purge written by single
  statements that flush nothing else — and a store in memory, emptied between requests by `kernel.reset`.
- `Application\TokenHasher`, the HMAC-SHA-256 of the identifier and the secret under a key of at least 32 bytes,
  written `$hmac-sha256$v=1$<Base64>` so that it names its algorithm and version, compared in constant time; the key
  rotates, `hash_key` an ordered list whose first key hashes new tokens and whose others, up to seven, still verify the
  tokens issued under them, every key computed whichever matches; `Application\OwnerTypes`, the owner types of
  `owner_types` and the scopes each may hold;
  `Application\ScopeCatalogue` and `ScopeDefinition`, the scopes declared in configuration with their descriptions and
  translatable labels; `UnknownScope`.
- `Application\ApiTokens`: create a token for a member or a typed owner, within the scopes of its type, shown once as
  an `IssuedToken`, find, get by owner, revoke, revoke every active token of an owner, rotate with an optional grace, purge past a retention, verify a presented token — its use written at most once per throttle
  interval, the client's address kept only through a `ClientAddressAnonymizer` —, and revoke a token reported leaked;
  `InvalidCredentials` with its `CredentialFailure`, `TokenNotFound`. The events `TokenCreated`, `TokenRevoked`,
  `TokenUsed` and `TokenLeaked` carry the token's identifier only.
- `Infrastructure\Security\ApiTokenHandler`, the token handler of Symfony's `access_token` authenticator, which gives
  the entry point, the challenge of RFC 6750 and the plain text `401`; a typed owner loaded by the user provider of its
  type, through `phil_api_keys.owner_providers`; `phil_api_keys.token_extractor` for
  `Authorization: Bearer` or another header; `TokenAuthenticationListener`, the scopes as `ROLE_API_SCOPE_*` roles,
  the token's identifier and its owner type on the security token and the request; `ProblemErrorResponder`, the `401` as problem details
  with `phil/api-contract-bundle`, an entry point and a failure handler. `ScopeVoter` (`API_SCOPE`) and
  `#[RequiresScope]`, answered `401` without authentication and `403` with the `insufficient_scope` challenge.
- `Infrastructure\SecretScanning\GitHub`: the endpoint of GitHub's secret scanning partner program, disabled by
  default, `GitHubSecretScanningController` and its route — the ECDSA signature verified before the body is read, each
  token of the application revoked, the matches labelled by the SHA-256 of their token —; `GitHubPublicKeys`, GitHub's
  keys in a cache pool, fetched again at most once per interval; `LeakReport` and `UnreadableReport`; two fuzz targets.
- `Infrastructure\RateLimit\TokenKey`, the key strategy of `phil/rate-limit-bundle` by token, for its limiters and those
  that count on a meter of `phil/usage-meter-bundle`;
  `Infrastructure\IpAnonymizer\AnonymizedClientAddress`, the address of the last use through `phil/ip-anonymizer`.
- The commands `phil:api-keys:create`, `phil:api-keys:list`, `phil:api-keys:revoke` and `phil:api-keys:purge-expired`,
  with `--owner-type` for typed owners and `--owner` to revoke every token of an owner.
- The members' page, and the page of another owner's tokens for those the application's voters grant
  `PHIL_API_KEYS_MANAGE` on that owner: `CreateTokenType`, offering the scopes of the owner type, `TokenPageController`
  with its routes, the templates `tokens.html.twig` and
  `_tokens.html.twig` in classless HTML for phil-ux, CSRF on creation and revocation, `Cache-Control: no-store`; the
  translations in English and French.
- The configuration `phil_api_keys` and its wiring, the Doctrine mapping prepended; a benchmark of the authenticator.
- The Flex recipe in `recipe/0.1`: the bundle registered, `config/packages/phil_api_keys.yaml` with the prefix and
  the key of the hashes read from `API_KEYS_HASH_KEY`, that variable added to `.env`.
- The documentation in `doc/`, one file per topic, indexed by the README.
