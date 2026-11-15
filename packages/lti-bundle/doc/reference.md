# Reference

Every public type of the bundle, with its role. A type marked internal is not part of the public API.

## Types

| Type | Kind | Role |
|---|---|---|
| `Phil\LtiBundle\Application\Contract\KeySealer` | `interface` | Seals the private keys the bundle keeps in its table, and opens them: the port to a key management service. The key identifier is authenticated with the sealed key, so that a sealed key copied to another row does not open. |
| `Phil\LtiBundle\Application\Contract\LaunchMessages` | `interface` | What the platform launches, as the application decides it: the application implements it when the platform side is enabled. The bundle checks the request, the tool and the redirect address before it asks, and signs the message. |
| `Phil\LtiBundle\Application\Contract\LaunchUsers` | `interface` | The user a verified launch signs in, on the tool: the application's own account for the person, found or made from the launch. Without one, the bundle signs in an `LtiUser` that lives in the session only. |
| `Phil\LtiBundle\Application\Contract\PendingScores` | `interface` | The scores the tool still has to send, kept until the platform takes them: the message of the bus carries only their identifier. |
| `Phil\LtiBundle\Application\Event\DeepLinkingReturned` | `final class` | A deep linking response the platform verified: a listener places the items — resource links, line items — and answers the browser, or the bundle shows a short page. |
| `Phil\LtiBundle\Application\Event\LaunchReceived` | `final class` | A launch the tool verified, its user signed in: a listener answers it — the page of the resource link, the picker of a deep linking request —, or the bundle sends the browser to the target of the launch. |
| `Phil\LtiBundle\Application\Event\LineItemDeleted` | `final readonly class` | A line item a tool deleted on the platform, with its scores. |
| `Phil\LtiBundle\Application\Event\LineItemSaved` | `final readonly class` | A line item a tool created or changed on the platform. |
| `Phil\LtiBundle\Application\Event\ScorePublished` | `final readonly class` | A score the tool sent, taken by the platform. |
| `Phil\LtiBundle\Application\Event\ScoreReceived` | `final readonly class` | A score a tool sent the platform, kept as the learner's latest on the line item: the application's gradebook listens. |
| `Phil\LtiBundle\Application\Message\PublishScore` | `final readonly class` | Sends a pending score to its platform: the message carries the identifier of the score only, which the handler reads from the store. |
| `Phil\LtiBundle\Application\PendingScore` | `final readonly class` | A score the tool sends to a line item of a platform, kept until it is sent. |
| `Phil\LtiBundle\Application\ScorePublisher` | `final readonly class` | Sends the scores of the tool to the platforms: a score is kept as pending, then sent by a message of the bus that carries its identifier only — or at once, without a bus —, and forgotten once the platform takes it. A failure of the network or of the platform throws, so that the bus tries again; a score that can never be sent — its platform no longer registered — is forgotten. |
| `Phil\LtiBundle\Infrastructure\Configuration\Registrations` | `final class` | Builds the registrations and the keys of the configuration, when the container makes the services. |
| `Phil\LtiBundle\Infrastructure\Console\PurgeCommand` | `final readonly class` | Removes the nonces, login states and access tokens that expired, as a scheduled task runs it: the states when the tool side is enabled, the tokens when the platform side is. |
| `Phil\LtiBundle\Infrastructure\Console\RotateKeysCommand` | `final readonly class` | Rotates the signing key — at once, or with `--if-due` only once it is older than the configured lifetime, as a scheduled task runs it — and removes the keys retired before the overlap. |
| `Phil\LtiBundle\Infrastructure\Console\SchemaCommand` | `final readonly class` | Shows the SQL that creates the bundle's tables missing from its connection, and runs it with `--force`: for an application without the ORM, or before its first migration. A table that is there is left as it is. |
| `Phil\LtiBundle\Infrastructure\Controller\AgsController` | `final readonly class` | The Assignment and Grade Services of the platform over HTTP: the line items of a context — listed with their filters and pages, read, created, changed, deleted —, the scores sent to a line item, and its results. Each call carries a bearer token of the scope it needs; a context or a line item that is not the tool's answers 404. Every change is told to the application by an event. |
| `Phil\LtiBundle\Infrastructure\Controller\JwksController` | `final readonly class` | The key set of the application, for the other side to verify what it signs: public for five minutes in caches. |
| `Phil\LtiBundle\Infrastructure\Controller\NrpsController` | `final readonly class` | The Names and Role Provisioning Service of the platform over HTTP: the members of a context, a page at a time, the next page in the `Link` header; a context that is not the tool's answers 404. |
| `Phil\LtiBundle\Infrastructure\Controller\PlatformLaunchController` | `final readonly class` | The browser routes of the platform: the authorization endpoint, which answers the tool's authentication request with the signed launch for the person signed in, and the return of a deep linking response. |
| `Phil\LtiBundle\Infrastructure\Controller\ServiceResponses` | `final class`, internal | The answers of the services of the platform: JSON of their media type, errors with the `WWW-Authenticate` of RFC 6750. |
| `Phil\LtiBundle\Infrastructure\Controller\TokenController` | `final readonly class` | The token endpoint of the platform, as RFC 6749 answers: the token in JSON, never cached; an error with its code, 401 for a client that does not authenticate and 400 otherwise. |
| `Phil\LtiBundle\Infrastructure\Controller\ToolController` | `final readonly class` | The two routes of the tool: the login initiation, which sends the browser to the platform with an authentication request, and the launch, which the authenticator verified before: the application answers it through `LaunchReceived`, or the browser goes to the target of the launch. |
| `Phil\LtiBundle\Infrastructure\Dbal\DbalAccessTokens` | `final readonly class` | The access tokens the platform granted, in a table, by the hash of their value only. |
| `Phil\LtiBundle\Infrastructure\Dbal\DbalKeyStore` | `final class` | The keys of the application in a table, each private key sealed by the key management service as the JSON of its private JSON Web Key: the signing key is the newest key not retired, a first one made when the table holds none; a rotation retires the keys before, published for the overlap, then pruned; the keys opened are kept until `reset()`. |
| `Phil\LtiBundle\Infrastructure\Dbal\DbalLineItemStore` | `final readonly class` | The line items of the platform, in a table; deleting one deletes its scores in the same transaction. |
| `Phil\LtiBundle\Infrastructure\Dbal\DbalLoginStates` | `final readonly class` | The login states of the tool, in a table, kept by the SHA-256 of the state. A state is taken by deleting its row: of two launches with the same state, only the one whose delete removed the row goes on. |
| `Phil\LtiBundle\Infrastructure\Dbal\DbalNonces` | `final readonly class` | The nonces used once, in a table: a value is kept by its SHA-256 under its scope, and the primary key makes the check and the write one step. A row that expired is replaced in a second step, which the primary key keeps atomic too. |
| `Phil\LtiBundle\Infrastructure\Dbal\DbalPendingScores` | `final readonly class` | The scores the tool still has to send, in a table. |
| `Phil\LtiBundle\Infrastructure\Dbal\DbalScoreStore` | `final readonly class` | The latest score of each learner on each line item, in a table: the score as AGS writes it, read again by the library, and its moment to the microsecond for ordering. |
| `Phil\LtiBundle\Infrastructure\Dbal\LtiSchemaListener` | `final readonly class` | Adds the bundle's tables to the schema the ORM generates for their connection: `doctrine:migrations:diff` writes the migration that creates them. Registered on the ORM's `postGenerateSchema` event when the ORM is installed. |
| `Phil\LtiBundle\Infrastructure\Dbal\LtiTables` | `final readonly class` | The seven tables of the bundle under one prefix: the nonces used once, the login states of the tool, the access tokens of the platform, the keys, the line items and the scores of the platform, and the scores the tool still has to send. |
| `Phil\LtiBundle\Infrastructure\Dbal\Row` | `final class`, internal | Typed reads of a row, whatever type the driver gives: an integer may come as text, a float as text. |
| `Phil\LtiBundle\Infrastructure\DependencyInjection\DefaultsPass` | `final readonly class` | What the application gives the bundle, or its default: an empty roster when it has none, so that the services of the platform answer 404 for every context; a stop with a message when the platform side is enabled without `LaunchMessages`; and the recipes of the kit named after the components subdirectory of the Symfony UX Toolkit, unless the configuration names one. |
| `Phil\LtiBundle\Infrastructure\DependencyInjection\LtiServices` | `final class` | The services of the configuration: what both sides share — the stores of the nonces and the keys, the key sets of the other side, the verifier, the key set route, the form post page —, then the tool's and the platform's when enabled. |
| `Phil\LtiBundle\Infrastructure\Http\FormPostResponder` | `final readonly class` | Answers with a page whose form the browser posts to the other side — an `id_token`, a deep linking response —: the form submits itself through the bundle's Stimulus controller, and shows its button when scripts do not run. The page is never cached, never framed by another origin than the one it posts to, and sends no referrer. |
| `Phil\LtiBundle\Infrastructure\KeyManagement\KeyManagementSealer` | `final readonly class` | Seals the private keys by envelope encryption through Symfony's KeyManagement component, under a master key of the application's KMS — a fresh data key per private key, AES-256-GCM, the key identifier as additional data — and stores the envelope in base 64. The component is experimental in Symfony 8.2: this adapter is the only class that calls it. |
| `Phil\LtiBundle\Infrastructure\Memory\InMemoryPendingScores` | `final class` | Pending scores held in one process, for tests. |
| `Phil\LtiBundle\Infrastructure\Messenger\PublishScoreHandler` | `final readonly class` | Sends the pending score a message names. A refusal the platform will repeat — the platform no longer registered, a status of 400 to 499 other than 408 and 429 — is not tried again; any other failure is. |
| `Phil\LtiBundle\Infrastructure\Routing\PlatformUrls` | `final readonly class` | The addresses of the platform's services, for the claims of the launches the application builds: the endpoint of the Assignment and Grade Services of a context and of a line item, with the scopes the tool is registered for, and the memberships of the context. |
| `Phil\LtiBundle\Infrastructure\Security\LtiLaunchAuthenticator` | `final class` | Turns a launch the tool verifies into a security token: on the launch route, a POST with an `id_token` and a `state` is checked by `LaunchValidator`; its user is the application's, by `LaunchUsers`, or an `LtiUser`; the token is an `LtiToken` that carries the launch. The request then goes on to the launch controller. A launch that fails a check answers 401 with the reason, never with the token. |
| `Phil\LtiBundle\Infrastructure\Security\LtiToken` | `final class` | The security token of a verified launch: its user, its firewall, and the launch, so that the application reads the context, the roles and the endpoints of the services from `$token->launch`. |
| `Phil\LtiBundle\Infrastructure\Security\LtiUser` | `final readonly class` | The user of a launch when the application has no account of its own for it: named by the hashed issuer, client and subject of the launch, with `ROLE_LTI` and, from the roles of the launch, `ROLE_LTI_INSTRUCTOR` or `ROLE_LTI_LEARNER`. It lives in the session only. |
| `Phil\LtiBundle\Infrastructure\Security\LtiUserProvider` | `final readonly class` | The user provider of the `LtiUser`s a launch signs in: they live in the session only, so a refresh keeps them as they are and none is loaded by its identifier. A firewall of launches names it when the application has no `LaunchUsers`, in a chain with its own provider otherwise. |
| `Phil\LtiBundle\Infrastructure\Twig\KitComponents` | `final readonly class` | The names under which the recipes of the kit are installed, in the components subdirectory of the application's `ux_toolkit` configuration: with `ui`, `Button` is `ui:Button`. |
| `Phil\LtiBundle\Infrastructure\Twig\LtiExtension` | `final readonly class` | `phil_lti_kit()`, the name under which a recipe of the kit is installed, for the templates of the bundle: `{% component (phil_lti_kit('Button')) %}`. |
| `Phil\LtiBundle\PhilLtiBundle` | `final class` | `phil/lti` in Symfony: an application that is an LTI 1.3 tool, a platform, or both. |

## Routes

| Route | Method | File | Controller |
|---|---|---|---|
| `phil_lti_jwks` | GET | `jwks.php` | `JwksController` |
| `phil_lti_login` | GET, POST | `tool.php` | `ToolController::login()` |
| `phil_lti_launch` | POST | `tool.php` | `ToolController::launch()`, after `LtiLaunchAuthenticator` |
| `phil_lti_platform_authorize` | GET, POST | `platform.php` | `PlatformLaunchController::authorize()` |
| `phil_lti_platform_deep_linking` | POST | `platform.php` | `PlatformLaunchController::deepLinking()` |
| `phil_lti_platform_token` | POST | `platform.php` | `TokenController` |
| `phil_lti_platform_line_items` | GET, POST | `platform.php` | `AgsController::lineItems()` |
| `phil_lti_platform_line_item` | GET, PUT, DELETE | `platform.php` | `AgsController::lineItem()` |
| `phil_lti_platform_scores` | POST | `platform.php` | `AgsController::scores()` |
| `phil_lti_platform_results` | GET | `platform.php` | `AgsController::results()` |
| `phil_lti_platform_memberships` | GET | `platform.php` | `NrpsController` |

## Commands

| Command | What it does |
|---|---|
| `phil:lti:schema [--force]` | Shows, or creates, the tables missing from the connection. |
| `phil:lti:rotate-keys [--if-due]` | Makes a new signing key, now or once the current one reaches its lifetime, and removes the keys retired before the overlap. |
| `phil:lti:purge` | Removes the expired nonces, login states and access tokens. |

## Templates and translations

| Name | What it is |
|---|---|
| `@PhilLti/form_post.html.twig` | The page whose form posts a launch or a Deep Linking response; `action` and `fields`. |
| `@PhilLti/deep_linking_returned.html.twig` | The page a Deep Linking return ends on without a listener's answer; `result`. |
| `phil_lti` | The translation domain of both, in English and French. |
| `phil_lti_kit()` | The Twig function that names a recipe of the kit. |
| `@phil/lti-bundle` | The AssetMapper namespace of the Stimulus controller `auto-submit`. |

## Constants

| Constant | Value | Meaning |
|---|---|---|
| `LtiTables::DEFAULT_PREFIX` | `phil_lti_` | The default prefix of the tables. |
| `LtiTables::PREFIX_PATTERN` | `/^[a-z_][a-z0-9_]{0,39}$/D` | The shape of a prefix. |
| `LtiServices::STORE_DBAL`, `STORE_MEMORY` | `dbal`, `memory` | The values of `store`. |
| `LtiServices::KEYS_DBAL`, `KEYS_CONFIG` | `dbal`, `config` | The values of `keys.source`. |
| `LtiServices::SERVICE_HTTP_CLIENT`, `KEY_SET_HTTP_CLIENT` | `phil_lti.service_http_client`, `phil_lti.key_set_http_client` | The `SafeHttpClient` services of the calls to the services and of the key sets. |
| `ServiceResponses::MAX_BODY` | `65536` | The largest body a service reads. |
