# Installation

## Requirements

PHP 8.5 or later with the `intl` and `sodium` extensions, Symfony 8.2 or later. The bundle builds on two others, which
the application registers and configures first:

- `phil/open-badges-bundle`, for the issuer, its key and the status lists it publishes;
- `phil/paged-media-bundle`, for the PDF, and Gotenberg's URL when the credential is embedded or the PDF signed.

The register's pages need TwigBundle; without it, the routes have no controller and the rest works. The bundled storage
needs DoctrineBundle; without it, the application gives the repositories ([Extension points](extension-points.md)).

```sh
composer require phil/certificates-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\OpenBadgesBundle\PhilOpenBadgesBundle::class => ['all' => true],
    Phil\PagedMediaBundle\PhilPagedMediaBundle::class => ['all' => true],
    Phil\CertificatesBundle\PhilCertificatesBundle::class => ['all' => true],
];
```

The container refuses to build when either bundle it builds on is missing.

## The status lists

The bundle names its own `CertificateStatusLists` as the lists `phil/open-badges-bundle` publishes, unless the
application's configuration names another service under `phil_open_badges.status_lists.service`. An application that
also issues other badges keeps its own lists and gives them to `phil_certificates.status_lists.others`
([Revocation](revocation.md)).

## The schema

With DoctrineBundle, the entities `Course` and `Certificate` are mapped from `src/Domain/Model`, in the tables
`phil_certificates_course` and `phil_certificates_certificate`. Generate the migration as for the application's own
entities:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

On MySQL and MariaDB, whose default collations fold case and accents, `BinaryKeysListener` maps the keys the register
looks up — the code, the holder and the completion of a certificate, the slug and the completion source of a course,
the member and the source of a learning activity — in `utf8mb4_bin`, so that the members `Alice` and `alice` never read
each other's certificates. The identifiers, which foreign keys reference, keep the table's collation. The listener is
registered with DoctrineBundle; the migration that `doctrine:migrations:diff` writes changes the columns of an existing
schema.

### Instants in UTC

Every instant the bundle stores is written and read in UTC, whatever the time zone of the PHP process —
`phil_certificates_certificate`: `awarded_at`, `valid_until`, `revoked_at`, `reminded_at`; `phil_certificates_course`:
`ends_at`; `phil_certificates_activity`: `completed_at`. The columns are mapped as `datetime_utc_immutable` and the
queries that compare them pass the same type: a web server, a console command and a Messenger worker running in
different time zones read the same instant, and an expiry cannot move by a few hours. The SQL type is the one of
`datetime_immutable`, so the schema does not change. An instant read back is in UTC; display it in the reader's time
zone, as Twig's `date` filter does.

#### Upgrading from 0.2

A database written by 0.2 holds each instant in the time zone of the process that wrote it. When that zone was UTC,
there is nothing to do. Otherwise, convert the rows once, with the application stopped so that no row is written in both
forms, giving the zone the processes ran in — `America/Toronto` here:

```sql
-- MySQL and MariaDB: named zones need the time zone tables (mysql_tzinfo_to_sql).
UPDATE phil_certificates_certificate SET
    awarded_at = CONVERT_TZ(awarded_at, 'America/Toronto', 'UTC'),
    valid_until = CONVERT_TZ(valid_until, 'America/Toronto', 'UTC'),
    revoked_at = CONVERT_TZ(revoked_at, 'America/Toronto', 'UTC'),
    reminded_at = CONVERT_TZ(reminded_at, 'America/Toronto', 'UTC');
UPDATE phil_certificates_course SET
    ends_at = CONVERT_TZ(ends_at, 'America/Toronto', 'UTC');
UPDATE phil_certificates_activity SET
    completed_at = CONVERT_TZ(completed_at, 'America/Toronto', 'UTC');
-- PostgreSQL
UPDATE phil_certificates_certificate SET
    awarded_at = (awarded_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC',
    valid_until = (valid_until AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC',
    revoked_at = (revoked_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC',
    reminded_at = (reminded_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC';
UPDATE phil_certificates_course SET
    ends_at = (ends_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC';
UPDATE phil_certificates_activity SET
    completed_at = (completed_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC';
```

On the night the clocks go back, the hour between 01:00 and 02:00 occurs twice and a stored local time does not say
which one it was: a row of that hour may end an hour off. When processes wrote in different zones, rows cannot be told
apart: check the ends of validity due soon with `phil:certificates:status`.

## The routes

```yaml
# config/routes/phil_certificates.yaml
phil_certificates:
    resource: '@PhilCertificatesBundle/config/routes.php'
    prefix: /certificates
```

The routes of `phil/open-badges-bundle` are imported too, since the credentials point to its status lists. Every route
of the register is public: keep them outside a firewall that asks for a login. Set `framework.router.default_uri` to
the site's address, so that the URLs written in credentials and printed on certificates awarded from a command or a
worker are the ones the site serves.
