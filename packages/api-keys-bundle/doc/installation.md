# Installation

## Requirements

- PHP 8.5 or later, with the OpenSSL and Sodium extensions.
- Symfony 8.2: FrameworkBundle, SecurityBundle, and the components `security-core` and `security-http`.
- Doctrine ORM 3.7 with DoctrineBundle, for the store `doctrine`.
- Optional: `symfony/console` for the commands; `symfony/form`, `symfony/security-csrf` and `twig/twig` for the
  members' page; `phil/api-contract-bundle` for problem details; `phil/rate-limit-bundle` for rate limits by token,
  with `phil/usage-meter-bundle` for quotas on meters; `phil/ip-anonymizer` for the address of the last use;
  `symfony/http-client`, `symfony/cache` and `phil/url` for the endpoint of GitHub's secret scanning.

## Registering the bundle

```sh
composer require phil/api-keys-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\ApiKeysBundle\PhilApiKeysBundle::class => ['all' => true],
];
```

## The recipe

With Symfony Flex, the recipe registers the bundle, writes `config/packages/phil_api_keys.yaml` with the prefix `pat`
and the key of the hashes read from `API_KEYS_HASH_KEY`, and adds that variable, empty, to `.env`: fill it as below.
The members' page and its routes stay off until the application enables them. The recipe lives in the recipes
repository of PHIL, read once the application lists that repository in `extra.symfony.endpoint`; without it, register
the bundle and write the configuration by hand.

## The key

Each token is kept as the HMAC-SHA-256 of its identifier and secret under a key of at least 32 bytes. Keep it in the
secrets vault, never in the repository:

```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;' | bin/console secrets:set API_KEYS_HASH_KEY -
```

```yaml
# config/packages/phil_api_keys.yaml
phil_api_keys:
    prefix: acme
    hash_key: '%env(API_KEYS_HASH_KEY)%'
```

To change the key, put the new one first and keep the previous one after it: new tokens are hashed under the first
key, and the tokens issued under the previous one keep working. Drop the previous key once `max_lifetime` has passed,
when every token issued under it has expired:

```yaml
phil_api_keys:
    hash_key: ['%env(API_KEYS_HASH_KEY)%', '%env(API_KEYS_PREVIOUS_HASH_KEY)%']
```

Dropping a key refuses every token issued under it.

## The table

The bundle maps the entity `ApiToken` for Doctrine, table `phil_api_token`, indexed by owner and by expiry. Generate
the migration as for any entity:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

## The firewall

The API sits behind a stateless firewall whose `access_token` authenticator names the bundle's token handler and
extractor, and the routes that need a token require an authenticated user:

```yaml
# config/packages/security.yaml
security:
    firewalls:
        api:
            pattern: ^/api/
            stateless: true
            provider: app_users
            access_token:
                token_handler: phil_api_keys.token_handler
                token_extractors: [phil_api_keys.token_extractor]
                realm: api
    access_control:
        - { path: ^/api/, roles: IS_AUTHENTICATED_FULLY }
```

The firewall's user provider loads the user by the token's owner: the user identifier of the member who created it.
With `phil/api-contract-bundle`, name `phil_api_keys.problem_responder` as the firewall's `entry_point` and the
authenticator's `failure_handler` for problem details: see [Authentication and scopes](authentication.md#the-answers).

## The members' page

Enable it and import its routes behind the firewall of signed-in members: see [The members' page](members-page.md).

## GitHub's secret scanning

Optionally, GitHub revokes the tokens it finds in public repositories through an endpoint of the application: see
[Secret scanning](secret-scanning.md).

## The purge

Run `phil:api-keys:purge-expired` each day, from the scheduler or cron, to remove the tokens expired or revoked past
the retention: see [Console](console.md).
