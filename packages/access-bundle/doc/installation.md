# Installation

```sh
composer require phil/access-bundle
```

Then register the bundle in `config/bundles.php`:

```php
Phil\AccessBundle\PhilAccessBundle::class => ['all' => true],
```

The package needs PHP 8.5 or later, Symfony 8.2 or later and Doctrine DBAL 4.5 or later. The voters need
SecurityBundle; the Twig functions TwigBundle; the components Symfony UX Twig Component and the recipes of the PHIL UX
kit; the EasyAdmin voter EasyAdmin 5.8 or later. Each integration is registered only when its bundle is enabled.

## The routes

The forms of "view as this member" post to two routes, imported under a prefix, behind the firewall:

```yaml
# config/routes/phil_access.yaml
phil_access:
    resource: '@PhilAccessBundle/config/routes.php'
    prefix: /_access
```

They check a stateless CSRF token, `phil_access_view_as`, which the bundle adds to `framework.csrf_protection`; a site
whose CSRF protection is off is stopped at compile time with a message that says what to change.

## The table

The tuples live in the table `phil_relation_tuple` of the Doctrine connection `default`. With the ORM, the bundle adds
the table to the schema it generates, so that a migration creates it:

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Schedule `phil:access:purge` — daily is enough — to delete the tuples whose expiry has passed; they already count for
nothing.

## The recipes of the kit

The banner and the summary draw the recipes `Alert`, `Badge`, `Button` and `Table`:

```sh
php bin/console ux:install alert --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install table --kit=github.com/parler-haut-interagir-librement/ux-kit
```
