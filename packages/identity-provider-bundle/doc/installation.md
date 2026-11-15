# Installation

```sh
composer require phil/identity-provider-bundle
```

The Flex recipe of the bundle registers it, writes `config/packages/phil_identity_provider.yaml` with every optional
module off, and `config/routes/phil_identity_provider.yaml` with the routes of the modules turned on. The recipe lives
in the recipes repository of PHIL, read once the application lists that repository in `extra.symfony.endpoint`; without
it, register the bundle and write the configuration by hand, as below. The firewall stays the application's to write.

The package needs PHP 8.5 or later with the `mbstring`, `openssl` and `sodium` extensions, Symfony 8.2 or later,
Doctrine ORM 3.7 or later and `web-token/jwt-library` 4.2 or later. The Mailer component sends its emails, the Notifier component its text messages, the
KeyManagement component encrypts its secrets, StimulusBundle serves its passkey controller, and Symfony UX Twig
Component with the recipes of the kit draws its pages: each is optional.

## The bundle

```php
// config/bundles.php
Phil\IdentityProviderBundle\PhilIdentityProviderBundle::class => ['all' => true],
```

The bundle maps the tables of the modules turned on for Doctrine and prepends the password hasher of its users:
Argon2id (`sodium`). Generate the migration of the tables with `bin/console doctrine:migrations:diff`.

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
                max_attempts: 5
```

`login_throttling` limits the attempts per address and per network address in front of every authenticator; the
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

Run `phil:identity-provider:rotate-keys` daily when the provider is on, and `phil:identity-provider:purge` daily to
remove expired codes and tokens and idle sessions, from the Scheduler component or a cron.
