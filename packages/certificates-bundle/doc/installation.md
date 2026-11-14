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
