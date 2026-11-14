# Reference

| Class | Role |
|---|---|
| `PhilOpenBadgesBundle` | The configuration, checked when the container is built, and the wiring. |
| `Application\Securing` | Secures the issuer's credentials as configured. |
| `Application\SecuredCredential` | A secured credential: its content and its media type. |
| `Application\Contract\StatusLists` | The port of the application's status lists. |
| `Application\StatusListContent` | The set indexes and the purpose of a list. |
| `Infrastructure\Routing\StatusListUrls` | The URL of a list, the entry of a revocable credential, the list of an entry, read by `phil/url`. |
| `Infrastructure\Http\IssuerController` | The profile and the JSON Web Key Set. |
| `Infrastructure\Http\StatusListController` | A status list as a secured credential. |
| `Infrastructure\Http\VerificationController` | The verification page. |
| `Infrastructure\Http\CredentialCheck`, `Infrastructure\Http\CheckResult` | What the page checks, and what it shows. |
| `Infrastructure\Verification\RemoteVerificationMethods` | The keys of other issuers, read from their profiles or DID documents through `phil/safe-http` and cached. |
| `Infrastructure\Http\VerificationRateLimit` | The rule `_rate_limit` given to a check of the page when a limiter is configured. |
| `Infrastructure\DependencyInjection\VerificationRateLimitPass` | Stops the build when that limiter is not declared. |
| `Infrastructure\DependencyInjection\Contexts` | Internal: the JSON-LD contexts read from the application's files. |
| `Infrastructure\DependencyInjection\OpenBadgesServices`, `Infrastructure\DependencyInjection\Keys` | Internal: the services and the factories of the key's services. |

| Route | Path | File |
|---|---|---|
| `phil_open_badges_issuer` | `/issuer` | `config/routes.php` |
| `phil_open_badges_issuer_keys` | `/issuer/keys` | `config/routes.php` |
| `phil_open_badges_status_list` | `/status/{list}` | `config/routes.php` |
| `phil_open_badges_verify` | `/verify` | `config/verification_routes.php` |
