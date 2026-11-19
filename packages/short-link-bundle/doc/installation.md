# Installation

```sh
composer require phil/short-link-bundle
```

Then register the bundle in `config/bundles.php`, unless Symfony Flex did:

```php
Phil\ShortLinkBundle\PhilShortLinkBundle::class => ['all' => true],
```

phil/redirects-bundle is installed with it, for its resolver; it needs not be registered.

## The domains

Each short link domain points to the application, as its other hosts do: a DNS record, a certificate, and the host
among those the web server sends to Symfony. The bundle answers a request on one of these hosts before the routing,
so the routes of the site never see it; a request on any other host goes on untouched.

```yaml
# config/packages/phil_short_link.yaml
phil_short_link:
    domains:
        - { host: go.example.com }
        - { host: lien.example.fr, locale: fr }
```

When the application lists its trusted hosts (`framework.trusted_hosts`), the short link domains belong in that list.

## The table

The links live in one table, `phil_short_link`, mapped by the bundle for Doctrine ORM. Create it with a migration:

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

On MySQL, whose default collation compares text without case, two codes that differ only by their case are one: the
unique index refuses the second, and the bundle draws another code or refuses the chosen one. On PostgreSQL and SQLite,
they are two codes.

## The administration

The administration is mounted by the application, under a prefix of its choice, behind its firewall:

```yaml
# config/routes/phil_short_link.yaml
phil_short_link_admin:
    resource: '@PhilShortLinkBundle/config/routes/admin.php'
    prefix: /admin/short-links
```

It needs `twig/twig`, `symfony/form`, `symfony/security-csrf` and the translator. Without them, set
`admin.enabled` to `false`.

## Requirements

PHP 8.5 or later, Symfony 8.2 or later, Doctrine ORM 3.7 and DBAL 4.5 or later. On PHP 8.5, `SortDirection` comes from
`symfony/polyfill-php86`, installed with the bundle.
