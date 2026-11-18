# Changelog

All notable changes to `phil/lti-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-17

The next version is 0.2.0-alpha: the ports `PendingScores` and `DeepLinkingReturns` change or appear, the schema gains a
table and four columns, and it requires `phil/lti` 0.2.

### Added

- `DeepLinkingHandler`, the port `DeepLinkingReturns` with `DbalDeepLinkingReturns` and `InMemoryDeepLinkingReturns`,
  and `StoredDeepLinkingReturn`: a verified deep linking response is kept, for `platform.deep_linking_retention`
  seconds, before `DeepLinkingReturned` tells the application, then marked handled; `DeepLinkingHandler::resume()` and
  `phil:lti:resume-deep-linking` tell it again from the claims kept, without the token, whose nonce stays used once.
- `DeepLinkingReturned::$key`, the stable key of the response, and `DeepLinkingReturned::$resumed`.
- `ScorePublisher::recover()` and `phil:lti:recover-scores`: the scores whose message the bus never took — or, without
  a bus, that the platform could not take yet — are claimed and sent again after a delay, idempotently.
- The event `ScoreRefused` and the configuration `tool.refused_score_retention`: a score the platform refused for good
  is kept as refused, with its reason, and `phil:lti:purge` removes it after the retention, writing its identifier,
  platform, client, moment and reason as the last trace.
- `ScorePublisher::isFinal()`, the failures the platform will repeat.
- The table `deep_linking` and the columns `dispatched_at`, `claimed_at`, `refused_at` and `refusal` of
  `pending_score`, with two indexes.

### Changed

- `PendingScores` gains `markDispatched()`, `markRefused()`, `claim()` and `refused()`, and `PendingScore` its
  `dispatchedAt`, `refusedAt` and `refusal`; a store of the application's own implements them.
- `ScorePublisher::publish()` records that the bus took the message, and gives the identifier of the score when the
  transport refuses it, leaving it to the recovery; a score of a platform no longer registered, or refused for good, is
  marked refused instead of being deleted, and never sent again.
- `PublishScoreHandler` reads the failures that are final from `ScorePublisher::isFinal()`.
- `DbalScoreStore::record()` keeps a score by a write guarded by the moment kept, an insert, and the guarded write
  again when another write inserted first, so that a score never replaces a more recent one on SQLite, MySQL, MariaDB
  and PostgreSQL; it answers whether it kept the score, and a stale score answers 409.
- `AgsController::lineItems()` always writes the `next` link when there is a next page, from the limit the page was cut
  at: also without a `limit`, and with a `limit` beyond 1,000.
- `PlatformLaunchController` hands a verified deep linking response to `DeepLinkingHandler`.
- `phil:lti:schema` adds to the tables there the columns and indexes they lack, besides creating the tables missing;
  `phil:lti:purge` also removes the deep linking responses expired and the scores refused beyond the retention.
- It requires `phil/lti` 0.2.
- `FormPostResponder` draws a nonce for each response, allows it alone in the `script-src` of the page's policy, and
  gives it to the template as `csp_nonce`; `@PhilLti/form_post.html.twig` posts its form by an inline script of its
  new block `auto_submit` under that nonce, without the application's importmap. The Stimulus controller `auto-submit`
  leaves alone a form already sent, marked `data-phil-lti-submitted`.

### Fixed

- A score sent with a token of the `score` scope alone, as `AgsClient::publishScore()` asks for, is answered 204: the
  line item of `ScoreReceived` is read from the store, where `AgsController::scores()` asked for the line item scopes,
  answered with an error once the score was kept, and left the tool to keep the score as pending.
- The page that posts a launch or a deep linking response submits itself without the application's scripts: its
  Stimulus controller never started, since the template loads no importmap, and the page stayed on its message.

## 0.1.0-alpha - 2026-11-15

### Added

- `PhilLtiBundle` and its configuration `phil_lti`: the store, `dbal` or `memory`, the connection, the table prefix, the
  clock, the cache, the HTTP client under the `phil/safe-http` clients, the Messenger bus, development addresses on
  `localhost`, the subdirectory of the kit, the keys, the tool and its platforms, the platform, its issuer, token endpoint and tools; checked when the
  container is built.
- The tool: `ToolController` with the routes of the login initiation and of the launch, `LtiLaunchAuthenticator`, which
  turns a verified launch into an `LtiToken` carrying it, `LtiUser` and `LtiUserProvider` for a user that lives in the
  session, the port `LaunchUsers` for the application's accounts, the event `LaunchReceived`.
- Scores of the tool: `ScorePublisher`, the port `PendingScores` with `DbalPendingScores` and `InMemoryPendingScores`,
  the message `PublishScore` carrying an identifier only, `PublishScoreHandler`, which does not retry a refusal the
  platform will repeat, and the event `ScorePublished`.
- The platform: `PlatformLaunchController` with the authorization endpoint and the Deep Linking return, the port
  `LaunchMessages`, `TokenController`, `AgsController`, `NrpsController`, `ServiceResponses`, `PlatformUrls`, the events
  `DeepLinkingReturned`, `LineItemSaved`, `LineItemDeleted` and `ScoreReceived`, and an empty roster until the
  application gives its own.
- `JwksController`, the key set route, and `FormPostResponder`, the page whose form posts a launch or a Deep Linking
  response, never cached, framed only by the origin it posts to, without referrer.
- The stores on Doctrine DBAL: `LtiTables`, `DbalNonces`, `DbalLoginStates`, `DbalAccessTokens`, `DbalLineItemStore`,
  `DbalScoreStore`, `DbalPendingScores` and `DbalKeyStore`; `LtiSchemaListener`, `phil:lti:schema` and `phil:lti:purge`.
- Keys: `DbalKeyStore`, which makes a first key, rotates and prunes, the port `KeySealer` and `KeyManagementSealer` over
  Symfony's KeyManagement, `phil:lti:rotate-keys`; keys from the environment through `Registrations::keys()`; every key
  opened is a JSON Web Key of `web-token/jwt-library`, of 2048 to 8192 bits.
- The services `phil_lti.service_http_client` and `phil_lti.key_set_http_client`, the `SafeHttpClient` of
  `phil/safe-http` that every outbound request goes through.
- The template `@PhilLti/form_post.html.twig` with the recipe `Button` of the kit, `KitComponents`, `LtiExtension` and
  `phil_lti_kit()`, the Stimulus controller `auto-submit` served by AssetMapper, the template
  `@PhilLti/deep_linking_returned.html.twig`, and the translations `phil_lti` in English and French.
- The routes in `config/routes/`: `tool.php`, `platform.php` and `jwks.php`.
- Tests on SQLite, MySQL 8.4 and PostgreSQL 17, a test application that launches itself, and the Flex recipe in
  `recipe/0.1`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
