# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later with `ext-sodium` and `ext-openssl` | Readonly classes, typed constants; libsodium for the local `sodium://` KMS and the base64 of the values, OpenSSL for the AES-256-GCM of the envelopes |
| `symfony/key-management` 8.2 | Envelope encryption through a KMS, the application's or a local one; the component is experimental |
| `doctrine/orm` 3.7, `doctrine/collections` 3.1 | The table of secrets |
| `symfony/polyfill-php86` | The `SortDirection` enum on PHP 8.5 |
| `symfony/config`, `symfony/dependency-injection` 8.2 | The configuration and services |
| `psr/clock` | The times rows and events are written |
| `psr/log` | The optional logger auditor, and the failures of the revocation at the service |
| `phil/privacy-bundle` (optional) | Export and erasure with the account |
| `symfony/console` (optional) | The commands |
| `symfony/lock` (optional) | One process at a time refreshes a token |

The application provides the service `clock` (Symfony's `ClockInterface`), which the full stack framework registers,
and DoctrineBundle for the entity manager; with `log_events`, the service `logger`. With `symfony/lock` and
`framework.lock` configured, refreshes run under FrameworkBundle's lock factory.

Symfony's KeyManagement component is experimental in Symfony 8.2: its API may change in a minor version, outside
Symfony's backward compatibility promise. The bundle calls it from one adapter only, and its sealed format names the
algorithm of each value, so that a change of the component is a change of that adapter.

## Installing

```sh
composer require phil/user-secrets-bundle
```

The Flex recipe of the bundle registers both bundles, writes `config/packages/key_management.yaml` and
`config/packages/phil_user_secrets.yaml` as the next section shows, and adds an empty `USER_SECRETS_KEY_2026` to
`.env`, to fill with a generated master key. Without Symfony Flex, register the bundles:

```php
// config/bundles.php
return [
    // …
    Symfony\Component\KeyManagement\KeyManagementBundle::class => ['all' => true],
    Phil\UserSecretsBundle\PhilUserSecretsBundle::class => ['all' => true],
];
```

## The KMS and its master key

The bundle seals through a client of Symfony's KeyManagement component. With no KMS of its own, an application
declares the component's local `sodium://` client: generate a master key and keep it where the application keeps its
other secrets — an environment variable set by the host, or the Symfony secrets vault —, never in the repository:

```sh
bin/console phil:user-secrets:generate-key
bin/console secrets:set USER_SECRETS_KEY_2026
```

```yaml
# config/packages/key_management.yaml
key_management:
    clients:
        local: 'sodium://?keys[k2026]=%env(USER_SECRETS_KEY_2026)%'

# config/packages/phil_user_secrets.yaml
phil_user_secrets:
    current_key: k2026
```

To seal through the application's KMS instead — AWS, Azure, Google Cloud, Vault —, declare its client under
`key_management.clients` and name one of its master keys in `current_key`; with several clients, name the one that
seals in `kms`, such as `key_management.vault`.

Losing a master key loses every secret it sealed: back it up apart from the database. [Encryption and key
rotation](encryption.md) explains how to replace it.

## The table

The bundle maps its row for Doctrine itself — no `mappings` entry to write. Create the table with the application's
migrations:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

The table `phil_user_secret` is keyed by `owner` and `name`; it holds `service`, `kind`, `key_id`, `ciphertext`,
`format`, `expires_at`, `created_at`, `updated_at`, `last_used_at` and `revoked_at`.

### Instants in UTC

Every instant the bundle stores is written and read in UTC, whatever the time zone of the PHP process —
`phil_user_secret`: `expires_at`, `created_at`, `updated_at`, `last_used_at`, `revoked_at`. The columns are mapped as
`datetime_utc_immutable` and the queries that compare them pass the same type: a web server, a console command and a
Messenger worker running in different time zones read the same instant, and an expiry cannot move by a few hours. The
SQL type is the one of `datetime_immutable`, so the schema does not change. An instant read back is in UTC; display it
in the reader's time zone, as Twig's `date` filter does.

#### Upgrading from 0.1

A database written by 0.1 holds each instant in the time zone of the process that wrote it. When that zone was UTC,
there is nothing to do. Otherwise, convert the rows once, with the application stopped so that no row is written in both
forms, giving the zone the processes ran in — `America/Toronto` here:

```sql
-- MySQL and MariaDB: named zones need the time zone tables (mysql_tzinfo_to_sql).
UPDATE phil_user_secret SET
    expires_at = CONVERT_TZ(expires_at, 'America/Toronto', 'UTC'),
    created_at = CONVERT_TZ(created_at, 'America/Toronto', 'UTC'),
    updated_at = CONVERT_TZ(updated_at, 'America/Toronto', 'UTC'),
    last_used_at = CONVERT_TZ(last_used_at, 'America/Toronto', 'UTC'),
    revoked_at = CONVERT_TZ(revoked_at, 'America/Toronto', 'UTC');
-- PostgreSQL
UPDATE phil_user_secret SET
    expires_at = (expires_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC',
    created_at = (created_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC',
    updated_at = (updated_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC',
    last_used_at = (last_used_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC',
    revoked_at = (revoked_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC';
```

On the night the clocks go back, the hour between 01:00 and 02:00 occurs twice and a stored local time does not say
which one it was: a row of that hour may end an hour off. When processes wrote in different zones, rows cannot be told
apart: store the credentials whose expiry matters again.
