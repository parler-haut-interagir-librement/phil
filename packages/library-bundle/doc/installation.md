# Installation

```sh
composer require phil/library-bundle
```

The bundle needs PHP 8.5 or later, Symfony 8.2 or later, Doctrine ORM 3.7 and DBAL 4.5 or later, Twig and Symfony UX
Twig Components. With Symfony Flex, the recipe registers the bundle and writes its configuration and routes; otherwise:

```php
// config/bundles.php
Phil\LibraryBundle\PhilLibraryBundle::class => ['all' => true],
```

## The schema

With DoctrineBundle, the bundle maps its entities itself; the application's migrations create three tables,
`phil_library_title`, `phil_library_copy` and `phil_library_loan`:

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Moments are kept in `datetime_utc_immutable` columns. A copy's barcode is unique; removing a title removes its copies
and their loans.

## The routes

```yaml
# config/routes/phil_library.yaml
phil_library:
    resource: '@PhilLibraryBundle/config/routes.php'
    prefix: /library
```

The staff pages ask for the roles of `roles` — `ROLE_LIBRARIAN` by default —, so the application's security gives
that role to its librarians. Without security, those pages are refused to everyone. See [Pages](pages.md).

## The recipes of the kit

The templates draw their parts with recipes of the PHIL UX kit, installed in the application by the Symfony UX
Toolkit:

```sh
php bin/console ux:install alert --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install table --kit=github.com/parler-haut-interagir-librement/ux-kit
```

## Optional packages

| Package | What it adds |
|---|---|
| `symfony/messenger` | The reminders sent as messages the application handles |
| `symfony/console` | `phil:library:send-reminders` and `phil:library:forget-history` |
| `phil/privacy-bundle` | A member's loans in the export and the erasure of their data |
| `phil/stockroom` | The stock moves of the copies that are items of a stockroom |
| `phil/stockroom-bundle` | The stockroom in the database, each movement locked to its item and kept in the loan's transaction when both share the connection |
| `easycorp/easyadmin-bundle` | The links of the library in a back office |
| `phil/search-bundle` | The titles in the site's search, indexed by the application |
