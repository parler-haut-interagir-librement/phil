# Installation

## Requirements

PHP 8.5 or later, Symfony 8.2 or later with FrameworkBundle — its forms, their CSRF protection and the translator —,
TwigBundle and Doctrine DBAL. Optional, each used when it is installed:

| Package | For |
|---|---|
| `symfony/security-bundle` | Naming the current user and checking the roles of the site's settings |
| `doctrine/orm` | Adding the tables to the schema the ORM generates |
| `phil/notifications-bundle` | Providing its recipients' preferences from the preferences |
| `phil/privacy-bundle` | Exporting and erasing a person's settings with their account |
| `phil/ux-css` | Dressing the pages and applying the display preferences |
| `symfony/stimulus-bundle`, `symfony/asset-mapper` | Applying a display preference as soon as it is chosen |

## Enabling the bundle

```sh
composer require phil/settings-bundle
```

```php
// config/bundles.php
Phil\SettingsBundle\PhilSettingsBundle::class => ['all' => true],
```

## The recipe

The Flex recipe of the bundle registers it in `config/bundles.php`, writes `config/packages/phil_settings.yaml`, which
gives the site's settings to `ROLE_ADMIN`, and `config/routes/phil_settings.yaml`, which imports the preferences and the
site's pages as below. The recipe lives in the recipes repository of PHIL, read once the application lists that
repository in `extra.symfony.endpoint`; without it, register the bundle and import the routes by hand.

## The tables

The values are kept in `phil_settings`, and the versions of the settings' migrations in `phil_settings_migrations`,
which the bundle adds to the schema the ORM generates for its connection:

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Without the ORM, create them from `Infrastructure\Doctrine\SettingTables::configure()`; see [Storage](storage.md).

At each deployment, after the database migrations, apply the migrations of the stored values; see
[Migrations](migrations.md):

```sh
php bin/console phil:settings:migrate
```

## The pages

Import the preferences, which visitors reach too, and the site's settings, behind the firewall of the administration:

```yaml
# config/routes/phil_settings.yaml
phil_settings:
    resource: '@PhilSettingsBundle/config/routes.php'
    prefix: /preferences

phil_settings_site:
    resource: '@PhilSettingsBundle/config/routes/site.php'
    prefix: /admin/settings
```

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/admin, roles: ROLE_ADMIN }
```

## The page's root element

Write the display preferences on the root element of the layout, so that the stylesheet applies them:

```twig
<html lang="{{ app.request.locale }}" {{ phil_document_attributes() }}>
```

See [Display preferences](display.md).
