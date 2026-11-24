# Installation

```sh
composer require phil/newsletter-archive
```

The bundle needs PHP 8.5 or later, with `ext-ctype`, `ext-dom`, `ext-libxml`, `ext-mbstring` and `ext-sodium`, and
`phil/newsletter-bundle` 0.3 or later, installed and configured: the archive reads its campaigns, lists, templates,
renderer and current site.

## The bundle

With Symfony Flex, the recipe registers the bundle and writes `config/packages/phil_newsletter_archive.yaml` and
`config/routes/phil_newsletter_archive.yaml`. Without it, register the bundle:

```php
// config/bundles.php
return [
    // …
    Phil\NewsletterArchive\PhilNewsletterArchiveBundle::class => ['all' => true],
];
```

## The routes

The pages are mounted under a prefix of the application's choice:

```yaml
# config/routes/phil_newsletter_archive.yaml
phil_newsletter_archive:
    resource: '@PhilNewsletterArchiveBundle/config/routes.php'
    prefix: /newsletter/archive
```

A command or a worker that writes an absolute address — the command prints the address of an issue and of a link —
reads the host from `framework.router.default_uri`.

## The tables

The archive keeps its issues in two tables, `phil_newsletter_archive` and `phil_newsletter_archive_tag`, on the
connection of [`connection`](configuration.md). With the ORM, `ArchiveSchemaListener` adds them to the schema it
generates, and `doctrine:migrations:diff` writes the migration that creates them. Without it, a migration calls
`ArchiveSchema::configure()` on the schema it builds:

```php
$schema = ArchiveSchema::configure($schema, $connection->getDatabasePlatform());
```

The instants are `datetime_utc_immutable` columns, written and read in UTC. On MySQL and MariaDB, the keys, slugs,
tags and hashes take the `utf8mb4_bin` collation, so that they compare byte by byte.
