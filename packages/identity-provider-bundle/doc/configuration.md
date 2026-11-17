# Configuration

The bundle's configuration lives under `phil_identity_provider`. Every option has a default but the few a module needs:
`passkeys.rp_id` and `passkeys.origins` for passkeys, `oidc.issuer` for the provider.

```yaml
# config/packages/phil_identity_provider.yaml
phil_identity_provider:
    secret: '%env(IDENTITY_SECRET)%'
    site_name: Example
    modules:
        accounts: true          # always on
        password: true
        passkeys: true
        totp: true
        recovery_codes: true
        login_link: false
        sms: false
        sessions: true
        step_up: true
        external: false
        oidc: true
    login_route: app_login
    default_target: /
    open_routes: [app_logout]
    mailer: { from: accounts@example.com }
    password: { min_score: 2, breach_check: true }
    lockout: { threshold: 5, base: 60, max: 3600 }
    passkeys: { rp_id: example.com, origins: ['https://example.com'] }
    oidc: { issuer: 'https://example.com' }
    secrets: { kms_key: identity }
```

## Common options

| Option | Default | What it does |
|---|---|---|
| `enabled` | `true` | Registers the bundle's services; `false` registers none. |
| `secret` | `%kernel.secret%` | Signs the links sent by email and keys the recovery codes; at least 32 characters, from the secrets vault. Changing it invalidates every link and recovery code. |
| `site_name` | `PHIL` | The name in emails, in the authenticator application and on passkeys. |
| `store` | `doctrine` | `doctrine`, or `memory` for tests: emptied between requests. |
| `entity_manager` | `default` | The Doctrine entity manager of the tables. |
| `clock` | `clock` | Service id of the PSR-20 clock. |
| `layout` | `@PhilIdentityProvider/layout.html.twig` | The template the pages extend. |
| `kit_subdirectory` | `null` | Where the recipes of the kit are; the subdirectory of `ux_toolkit` when null. |
| `registration` | `true` | Whether anyone may register. |
| `require_verified_email` | `false` | Whether an account signs in only once its address is verified. |
| `login_route` | `null` | The route of the sign-in form, offered for a re-authentication by password and after a failed external sign-in. |
| `default_target` | `/` | Where a sign-in by the bundle's authenticators goes when nothing asked for a page. |
| `open_routes` | `[]` | Routes reachable before the second step of a sign-in, by their exact names, such as the logout route; in development, `_wdt` and `_profiler` if the toolbar should show. Beside them, only the challenge, the code it posts and the sign-in with a passkey are open. |
| `mailer.from` | `null` | The sender of the emails; without it, nothing is sent and the events still are. |
| `audit` | `auto` | `auto` writes to `phil/audit-bundle` when it is installed, `none` nowhere, or the id of an `AuditRecorder`. |
| `secrets.kms_key` | `null` | The key of the KeyManagement component that encrypts TOTP secrets and private signing keys. With the modules `totp` or `oidc`, null is refused unless `secrets.allow_plaintext_secrets` is true; a key without `symfony/key-management` is refused. |
| `secrets.allow_plaintext_secrets` | `false` | Whether TOTP secrets and private signing keys may be kept in clear when no key is set, as in tests; the access controls of the database are then their only protection. |
| `secrets.encrypter`, `secrets.decrypter` | `key_management.encrypter`, `key_management.decrypter` | The services of the KeyManagement component. |

## Modules

| Module | Default | Needs | Tables |
|---|---|---|---|
| `accounts` | on, cannot be off | — | `phil_identity_account` |
| `password` | on | — | — |
| `passkeys` | off | `passkeys.rp_id`, `passkeys.origins` | `phil_identity_passkey` |
| `totp` | off | — | `phil_identity_totp`, `phil_identity_recovery_codes` |
| `recovery_codes` | off | `totp` | — |
| `login_link` | off | the firewall's `login_link` | — |
| `sms` | off | the Notifier component or `sms.sender` | — |
| `sessions` | on | — | `phil_identity_session` |
| `step_up` | on | the firewall's `re_authentication_entry_point` | — |
| `external` | off | a package implementing `ExternalProvider` | `phil_identity_linked` |
| `oidc` | off | `oidc.issuer` | `phil_identity_client`, `phil_identity_consent`, `phil_identity_code`, `phil_identity_token`, `phil_identity_signing_key` |

## Options of each module

| Option | Default | What it does |
|---|---|---|
| `password.min_score` | `2` | The lowest score of the strength estimator of `phil/passwords`, 0 to 4. |
| `password.breach_check` | `false` | Whether a new password is looked up in Pwned Passwords, by k-anonymity: only five characters of its SHA-1 leave the server. |
| `password.http_client` | `http_client` | The HTTP client of the breach check. |
| `lockout.threshold` | `5` | Failed password sign-ins in a row before the first lock. |
| `lockout.base` | `60` | Seconds of the first lock, doubled at each further failure. |
| `lockout.max` | `3600` | The longest lock. |
| `login_limiter.enabled` | `false` | Registers `phil_identity_provider.login_limiter`, the limiter the firewall's `login_throttling` names with its option `limiter`; needs `symfony/rate-limiter`. See [Limiting the sign-ins](accounts.md#limiting-the-sign-ins). |
| `login_limiter.max_attempts` | `5` | Sign-ins per identifier and address in each interval. |
| `login_limiter.network_max_attempts` | `25` | Sign-ins per address, or IPv6 /64 network, in each interval. |
| `login_limiter.account_max_attempts` | `50` | Sign-ins per identifier in each interval, whatever the address. Anyone who knows the identifier can spend it. |
| `login_limiter.interval` | `15 minutes` | The window of the counts. |
| `login_limiter.cache_pool` | `cache.rate_limiter` | The cache pool of the counts, shared by every server: in the `prod` environment, a pool local to each server fails the container build. |
| `login_limiter.lock_factory` | `null` | A `LockFactory` on a shared store, so that two sign-ins at once both count. |
| `login_limiter.single_server` | `false` | Accepts in the `prod` environment a cache pool local to the server, for an application on one server. |
| `links.verify_lifetime` | `86400` | Seconds a link that verifies an address lasts. |
| `links.reset_lifetime` | `3600` | Seconds a link that resets a password lasts. |
| `passkeys.rp_id` | `null` | The relying party identifier: the registrable domain of the site, such as `example.com`. |
| `passkeys.rp_name` | `null` | The name the authenticator shows; the site name when null. |
| `passkeys.origins` | `[]` | The origins the pages are served from, such as `https://example.com`. |
| `passkeys.require_user_verification` | `false` | Whether a passkey must verify the person by a PIN or a biometric. |
| `totp.issuer` | `null` | The issuer the authenticator application shows; the site name when null. |
| `totp.window` | `1` | The periods of 30 seconds accepted before and after the current one, 0 to 10. |
| `sms.sender` | `null` | Service id of an `SmsSender`; the texter of the Notifier component when null. |
| `sms.from` | `''` | The sender of the text messages, when the transport allows one. |
| `sms.message` | `Your code: {code}` | The text, `{code}` replaced by the code. |
| `sms.lifetime` | `600` | Seconds a code lasts. |
| `sms.attempts` | `5` | Tries a code allows. |
| `step_up.attribute` | `PHIL_STEP_UP` | The attribute the bundle's sensitive actions need: `PHIL_STEP_UP`, or `IS_AUTHENTICATED_RECENTLY` of Symfony Security. |
| `step_up.lifetime` | `300` | Seconds a proof grants `PHIL_STEP_UP`. |
| `sessions.touch_interval` | `300` | The fewest seconds between two writes of when a session was last seen. |
| `external.link_verified_email` | `false` | Whether an identity whose provider verified an address links to the account that verified the same address, instead of asking the person to sign in first. |
| `external.register` | `true` | Whether an unknown external identity creates an account. |
| `oidc.issuer` | `null` | The https address clients know the provider by, without a trailing slash. |
| `oidc.algorithm` | `RS256` | The algorithm of new signing keys: `RS256`, `ES256` or `EdDSA`. |
| `oidc.code_lifetime` | `60` | Seconds an authorization code lasts, at most 600. |
| `oidc.access_token_lifetime` | `600` | Seconds an access token lasts. |
| `oidc.id_token_lifetime` | `600` | Seconds an ID token lasts. |
| `oidc.refresh_token_lifetime` | `2592000` | Seconds a refresh token lasts. |
| `oidc.key_activation_delay` | `86400` | Seconds a new key is published before it signs. |
| `oidc.retired_key_retention` | `604800` | Seconds a retired key stays published. |
| `oidc.allow_localhost` | `false` | Whether redirect addresses on `localhost` over http are accepted, for development. |
| `oidc.lock_factory` | `lock.factory` | The `LockFactory` service that serializes the first signing key and each rotation; its store must be shared by every server. |

## A site that refuses some methods

A site that does not offer sign-in links nor text messages leaves `login_link` and `sms` off: their routes do not
exist, their controllers are not registered, and no page links to them.
