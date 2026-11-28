# Installation

## Requirements

- PHP 8.5 or later, with `ext-mbstring`, `ext-openssl` and `ext-sodium`;
- Symfony 8.2;
- phil/identity-provider-bundle, with its module `external` on and Symfony Security configured as its installation
  guide says;
- `web-token/jwt-library`, required by this package: Symfony's OpenID Connect pieces verify Google's tokens with it.

## The client at Google

In the Google Cloud console, under **APIs & Services → Credentials**, create an **OAuth client ID** of the type
**Web application**:

- **Authorized redirect URIs**: the bundle's callback, `https://example.com/login/with/google/callback`, as the router
  generates it — the address must match to the character;
- **Authorized JavaScript origins**: none are needed.

On the **OAuth consent screen**, the scopes the package asks for are `openid` and `email`, and `profile` when
`profile: true` is configured. They are non-sensitive scopes: no verification by Google is needed for them.

Keep the client ID in the environment and the client secret in Symfony secrets:

```sh
php bin/console secrets:set GOOGLE_CLIENT_SECRET
```

## The package

```sh
composer require phil/identity-provider-google-bundle
```

With Symfony Flex, the recipe registers the bundle, writes `config/packages/phil_identity_provider_google.yaml` and
`config/routes/phil_identity_provider_google.yaml`, and adds `GOOGLE_CLIENT_ID` to `.env`. Without it, register the
bundle and write the configuration:

```php
// config/bundles.php
return [
    // ...
    Phil\IdentityProviderBundle\PhilIdentityProviderBundle::class => ['all' => true],
    Phil\IdentityProviderGoogleBundle\PhilIdentityProviderGoogleBundle::class => ['all' => true],
];
```

```yaml
# config/packages/phil_identity_provider_google.yaml
phil_identity_provider_google:
    client_id: '%env(GOOGLE_CLIENT_ID)%'
    client_secret: '%env(GOOGLE_CLIENT_SECRET)%'
```

The bundle's module `external` must be on:

```yaml
# config/packages/phil_identity_provider.yaml
phil_identity_provider:
    modules:
        external: true
```

## The routes

The sign-in uses the bundle's routes: `/login/with/google` and `/login/with/google/callback`. The package adds one route
of its own, the endpoint of [Cross-Account Protection](risc.md), which answers only when `risc.enabled` is on:

```yaml
# config/routes/phil_identity_provider_google.yaml
phil_identity_provider_google:
    resource: '@PhilIdentityProviderGoogleBundle/config/routes.php'
    prefix: /identity/google
```

## The firewall

The bundle's `ExternalAuthenticator` answers the callback; it is already among the firewall's custom authenticators
when the bundle is installed. The RISC endpoint is called by Google, without a session: leave it to anonymous access.

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/identity/google/risc$, roles: PUBLIC_ACCESS }
```

## Worker mode

The services keep nothing from one request to the next: the discovery documents read in a request are forgotten by
`kernel.reset`, and the HTTP client forgets the addresses it resolved. The documents and keys stay in the cache pool.
