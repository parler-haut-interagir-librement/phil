# Changelog

All notable changes to `phil/lti-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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
