# Installation

```sh
composer require phil/identity-provider-bundle
```

The Flex recipe of the bundle registers it, writes `config/packages/phil_identity_provider.yaml` with every optional
module off, and `config/routes/phil_identity_provider.yaml` with the routes of the modules turned on. The recipe lives
in the recipes repository of PHIL, read once the application lists that repository in `extra.symfony.endpoint`; without
it, register the bundle and write the configuration by hand, as below. The firewall stays the application's to write.

The package needs PHP 8.5 or later with the `mbstring`, `openssl` and `sodium` extensions, Symfony 8.2 or later,
Doctrine ORM 3.7 or later and `web-token/jwt-library` 4.2 or later; the Rate Limiter component counts the
registrations and, with `login_limiter`, the sign-ins per identifier. The Mailer component sends its emails, the
Notifier component its text messages, the KeyManagement component encrypts its secrets, StimulusBundle serves its passkey controller, and Symfony UX Twig Component with the recipes of the kit draws its
pages: each is optional. The modules `totp` and `oidc` keep secrets, and refuse to start without `secrets.kms_key`
unless `secrets.allow_plaintext_secrets: true` says they may stay in clear.

## The bundle

```php
// config/bundles.php
Phil\IdentityProviderBundle\PhilIdentityProviderBundle::class => ['all' => true],
```

The bundle maps the tables of the modules turned on for Doctrine and prepends the password hasher of its users:
Argon2id (`sodium`). Generate the migration of the tables with `bin/console doctrine:migrations:diff`.

## Upgrading from 0.2

- On MySQL and MariaDB, the keys of the tables take the binary collation: run `bin/console doctrine:migrations:diff`,
  read the `ALTER TABLE … MODIFY … CHARACTER SET utf8mb4 COLLATE utf8mb4_bin` it writes for the columns listed in
  [Tables](reference.md#tables), and migrate. Rows that the former collation took for one — `App` and `app` — stay
  two rows. PostgreSQL and SQLite need no migration.
- Set `public_origin` or `framework.trusted_hosts`, as [above](#where-the-links-point): without either, the `prod`
  environment no longer boots.
- Registration is limited by default; `registration: true` and `registration: false` keep working, and
  `registration: { rate_limit: false }` turns the limits off.
- With the module `oidc`, the schema gains the table `phil_identity_assertion`, the columns `jwks`, `resources` and
  `backchannel_logout_uri` and `sector_identifier` of `phil_identity_client`, and the column `audience` of `phil_identity_token`, whose `account_id` becomes nullable: run
  `bin/console doctrine:migrations:diff` and migrate. Every new column is nullable; no row changes.
- A refresh token issued in a session is refused once the session ends, its family revoked
  (`oidc.refresh_token_bound_to_session`, on by default); set it to `false` to keep the former behaviour.
- `TokenController::AUTH_METHODS` is gone: the discovery document reads the methods from
  `oidc.client_authentication.methods`. `IssuedToken::accountId()` returns null for a token of the `client_credentials`
  grant, and `GrantRepository` gains `useAssertion()`, `revokeFamily()` and `isFamilyRevoked()`: an implementation of
  its own adds them. `Tokens::bearer()` gives a third member, the client of the token.
- The accounts gain the table `phil_identity_address`, and `phil_identity_account` the columns `email_canonical`
  (unique), `state` and `state_changed_at` in place of `disabled_at`; every `account_id`, and the `id` of
  `phil_identity_account`, holds up to 36 characters instead of a fixed 26. Run `bin/console doctrine:migrations:diff`,
  then complete the migration it writes so that the rows keep their meaning, before the new columns become required:

  ```sql
  UPDATE phil_identity_account SET email_canonical = email;
  UPDATE phil_identity_account SET state = 'suspended', state_changed_at = disabled_at WHERE disabled_at IS NOT NULL;
  ```

  With `accounts.strip_subaddress`, compute `email_canonical` with the bundle's `EmailCanonicalizer` instead, and settle
  first the accounts that share a mailbox. The passkeys gain `attestation_format` and `attestation_type`, which default
  to `none`.
- `Account::enable()` takes the moment of the change, as `disable()` does; `AccountRepository` gains
  `findByCanonicalEmail()`: an implementation of its own adds it. `Account::register()` and `changeEmail()` take the
  canonical form, last and optional; an identifier holds 1 to 36 letters, digits and hyphens.
- `Tokens`, `LogoutTokens` and `MetadataController` take a `SigningKeySource` — `SigningKeys` is one —, and
  `Jws::encode()` takes a `Signer` — `PrivateKey` is one: code that passed them a `SigningKeys` or a `PrivateKey`
  keeps working.
- With `links.mode: stored`, the schema gains `phil_identity_link_token`: run `bin/console doctrine:migrations:diff` and
  migrate. The links sent before the change of mode stop working.
- With the module `oidc`, the schema gains the table `phil_identity_revoked_family`, the marks of the families of tokens
  revoked: run `bin/console doctrine:migrations:diff` and migrate.
- Every instant column — `expires_at`, `revoked_at`, `created_at` and the others — takes the Doctrine type
  `datetime_utc_immutable`: the instants are written and read in UTC whatever the time zone of the process. The SQL
  type is the same, so `doctrine:migrations:diff` writes no change. A database written by processes whose time zone
  was not UTC holds local times: convert those columns to UTC once, before the upgrade goes live — for example, on
  MySQL, `UPDATE phil_identity_token SET expires_at = CONVERT_TZ(expires_at, 'America/Toronto', '+00:00')` for each
  instant column of the tables listed in [Tables](reference.md#tables), with the zone the processes ran in. A database
  written in UTC needs nothing.
- The account emails go through the transports of the Mailer component and the codes through the SMS transports of
  the Notifier component, never through Messenger: `MailerAccountMailer` takes the transports and the body renderer
  of Twig instead of `MailerInterface`, and the bundle hands `NotifierSmsSender` a texter without a bus. See [Why
  account emails and SMS never go through Messenger](links-and-sms.md#why-account-emails-and-sms-never-go-through-messenger).
- A client assertion names the issuer alone in its `aud`, as a string (draft-ietf-oauth-rfc7523bis): an assertion
  for the token endpoint, or with its audiences in an array, is refused with `invalid_client`. Update those clients, or
  set `oidc.client_authentication.legacy_assertion_audiences: true` while they are updated.
  `ProviderSettings::assertionAudiences()` gives the issuer alone unless that option is on.
- A client registered with `--pkce-optional` sends a `nonce` with every authorization request that carries no PKCE
  challenge; a request with neither goes back to it with `invalid_request` (RFC 9700, section 2.1.1).

## Where the links point

The links of the emails must never take the host a request names. Set the canonical address of the site, or the hosts
it answers, or both:

```yaml
# config/packages/phil_identity_provider.yaml
phil_identity_provider:
    public_origin: '%env(APP_PUBLIC_ORIGIN)%'   # https://example.com

# config/packages/framework.yaml
framework:
    trusted_hosts: ['^example\.com$']
```

In the `prod` environment the bundle refuses to boot when neither is set: see [Where the links
point](accounts.md#where-the-links-point).

## The firewall

The firewall names the bundle's user provider and user checker, the sign-in form of the application, and the
authenticators of the modules turned on:

```yaml
# config/packages/security.yaml
security:
    providers:
        accounts:
            id: phil_identity_provider.user_provider
    firewalls:
        main:
            lazy: true
            provider: accounts
            user_checker: phil_identity_provider.user_checker
            form_login:
                login_path: app_login
                check_path: app_login
                enable_csrf: true
            login_link:                       # the module login_link
                check_route: phil_identity_provider_login_link_check
                signature_properties: [userIdentifier, passwordChangedAt]
                lifetime: 600
                max_uses: 1
                check_post_only: true         # set by the bundle whatever is written here
            custom_authenticators:
                - Phil\IdentityProviderBundle\Infrastructure\Security\PasskeyAuthenticator     # passkeys
                - Phil\IdentityProviderBundle\Infrastructure\Security\OneTimeCodeAuthenticator # totp
                - Phil\IdentityProviderBundle\Infrastructure\Security\ExternalAuthenticator    # external
            entry_point: form_login
            re_authentication_entry_point: phil_identity_provider.re_authentication_entry_point
            recent_authentication_lifetime: 600
            logout:
                path: app_logout
            login_throttling:
                limiter: phil_identity_provider.login_limiter   # with login_limiter.enabled; else max_attempts: 5
```

`login_throttling` limits the attempts in front of every authenticator: by default per address and per identifier and
address, and with the bundle's limiter per identifier whatever the address too (see [Limiting the
sign-ins](accounts.md#limiting-the-sign-ins), with what `framework.trusted_proxies` must say behind a proxy); the
bundle's lock counts the failures of each account on top of it. `max_uses` of `login_link` needs a cache pool.

## The routes

```yaml
# config/routes/phil_identity_provider.yaml
phil_identity_provider:
    resource: .
    type: phil_identity_provider
```

The loader declares the routes of the modules turned on only, listed in the [Reference](reference.md#routes). A prefix
or a host is the application's: add `prefix:` to the import. The OpenID Connect discovery document must stay at
`/.well-known/openid-configuration` under the issuer.

## The pages and the recipes of the kit

The pages extend `@PhilIdentityProvider/layout.html.twig`, or the layout the option `layout` names, and draw their
parts with recipes of the PHIL UX kit, installed in the application by the Symfony UX Toolkit; nothing is rendered from
`vendor/`:

```sh
php bin/console ux:install alert --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install field --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install input --kit=github.com/parler-haut-interagir-librement/ux-kit
```

The recipes have the names, props and blocks of the Shadcn kit of the Toolkit, so a site on Tailwind installs them
from the Shadcn kit instead. They are found under the components subdirectory of the `ux_toolkit` configuration, as
`phil_identity_kit('Field:Label')` names them; in an environment where the Toolkit is not enabled, repeat it in the
option `kit_subdirectory`. Any template is replaced by one of the application at
`templates/bundles/PhilIdentityProviderBundle/`.

The passkey buttons are driven by the Stimulus controller `passkey` of the package `@phil/identity-provider-bundle`,
which the bundle adds to AssetMapper's paths; StimulusBundle enables it from `assets/package.json`.

## The sign-in page

The sign-in form is the application's. A page offering passkeys includes the bundle's button:

```twig
{{ include('@PhilIdentityProvider/passkey/_sign_in.html.twig') }}
<a href="{{ path('phil_identity_provider_login_link') }}">Sign in by email</a>
<a href="{{ path('phil_identity_provider_external_start', {provider: 'google'}) }}">Sign in with Google</a>
```

## The schedule

Run `phil:identity-provider:rotate-keys` daily when the provider keeps its keys in the database, and
`phil:identity-provider:purge` daily to remove expired codes, tokens and link tokens and idle sessions, from the
Scheduler component or a cron. With `oidc.signing_keys.automatic_rotation: false`, the scheduled rotation changes
nothing, and an operator rotates with `--force`.
