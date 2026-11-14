# Installation

## Requirements

PHP 8.5 or later, Symfony 8.2 or later, `phil/open-badges` 0.1.1 or later, `phil/url`, `phil/safe-http` and
Symfony's cache contracts. The verification page needs TwigBundle; without it, the page is not registered and the
rest works. A limit on its checks needs `phil/rate-limit-bundle`.

The bundle signs as an issuer the application names: registered without `issuer.id`, `issuer.name` and `key.secret`,
it stops the build and says which are missing.

```sh
composer require phil/open-badges-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\OpenBadgesBundle\PhilOpenBadgesBundle::class => ['all' => true],
];
```

## The key

The issuer signs with one key, kept as a secret:

```sh
php -r 'echo base64_encode(random_bytes(32)), "\n";'      # an Ed25519 seed
bin/console secrets:set OPEN_BADGES_SEED
```

```yaml
phil_open_badges:
    key:
        secret: '%env(base64:OPEN_BADGES_SEED)%'
```

An RSA key of 2048 bits or more, in PEM, signs JSON Web Tokens with `RS256`:
`{algorithm: RS256, secret: '%env(file:OPEN_BADGES_KEY)%', passphrase: '%env(OPEN_BADGES_PASSPHRASE)%'}`. Data
Integrity proofs need an Ed25519 key.

The key is read when a service first needs it, not when the container is built: the secret stays an environment
variable until then. Changing the key invalidates nothing already issued, but verifiers need the old public key to
check old credentials: keep the old key's credentials verifiable through `trusted_keys`.

## The routes

```yaml
# config/routes/phil_open_badges.yaml
phil_open_badges:
    resource: '@PhilOpenBadgesBundle/config/routes.php'
    prefix: /open-badges
phil_open_badges_verification:
    resource: '@PhilOpenBadgesBundle/config/verification_routes.php'
    prefix: /open-badges
```

## The contexts

The JSON-LD contexts of Open Badges 3.0 and CLR 2.0 are published by 1EdTech under its own terms. The application
downloads those it uses once, keeps them with its configuration, and names them in `contexts`:

```yaml
phil_open_badges:
    contexts:
        'https://purl.imsglobal.org/spec/ob/v3p0/context-3.0.3.json': '%kernel.project_dir%/config/jsonld/ob-v3p0-context-3.0.3.json'
        'https://purl.imsglobal.org/spec/clr/v2p0/context-2.0.1.json': '%kernel.project_dir%/config/jsonld/clr-v2p0-context-2.0.1.json'
```

Each context is downloaded from its own URL, which is where 1EdTech publishes it, and committed with the application:

```sh
mkdir -p config/jsonld
curl --fail --location -o config/jsonld/ob-v3p0-context-3.0.3.json https://purl.imsglobal.org/spec/ob/v3p0/context-3.0.3.json
curl --fail --location -o config/jsonld/clr-v2p0-context-2.0.1.json https://purl.imsglobal.org/spec/clr/v2p0/context-2.0.1.json
```

The files are read when the Data Integrity suite is first used, through the `ContextLoader` of `phil/open-badges`: each
is a JSON object with an `@context`, of 1 MiB at most, or that first use fails with an error that names the file. A context
named here is used in place of any `phil/open-badges` carries; the Verifiable Credentials 2.0 context comes with
`phil/open-badges`. A URL neither gives is refused, so nothing is fetched while a proof is made or checked. Securing
with JSON Web Tokens reads no context.

## The issuer's identifier

The issuer's identifier is the absolute URL of its profile: with the prefix `/open-badges`, it is
`https://<site>/open-badges/issuer`. The routes are public: keep them outside a firewall that asks for a login.

Set `framework.router.default_uri` to the site's address, so that the URLs of the status lists written in credentials
issued from a command or a worker are the ones the site serves.
