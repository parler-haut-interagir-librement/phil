# Installation

```sh
composer require phil/incident-mode-bundle
```

The bundle needs PHP 8.5 or later with `ext-intl`, Symfony 8.2 or later, Doctrine DBAL 4.5, Twig with the Symfony UX
Twig components, and `phil/incident-mode` 0.2. Symfony Flex registers the bundle and copies its recipe: the
configuration in `config/packages/phil_incident_mode.yaml` and the routes in `config/routes/phil_incident_mode.yaml`.
Without Flex, register it:

```php
// config/bundles.php
return [
    // …
    Phil\IncidentModeBundle\PhilIncidentModeBundle::class => ['all' => true],
];
```

The bundle uses these packages when they are installed:

| Package | For |
|---|---|
| `doctrine/orm` | The table added to the schema the ORM generates, so that `doctrine:migrations:diff` writes its migration |
| `symfony/console` | The commands `phil:incident:*` |
| `symfony/form` | The option `suspended_during` of the forms |
| `symfony/messenger` | The delayed message that lifts each incident at its end |
| `symfony/scheduler` | The recurring sweep for the messages a transport lost |
| `phil/ux-kit`, or the Shadcn kit of the Symfony UX Toolkit with `phil/ux-kit-shadcn` | The recipes the pages draw |
| `phil/audit-bundle` | Every start, extension and lifting written to its trail |
| `phil/privacy-bundle` | The incidents of a member in the export and the erasure of their data |

## The table

The incidents live in the table `phil_incident_activation` of the connection `phil_incident_mode.connection`, the
connection `default` unless the configuration names another. With the ORM, the library's `IncidentSchemaListener` adds
the table to the schema the ORM generates for that connection, and the migrations write it:

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

When a version of the library adds a column or an index, `doctrine:migrations:diff` writes the migration that adds it.
An application on DBAL without the ORM creates the table in one of its own migrations with
`Phil\IncidentMode\Dbal\IncidentTables::configure($schema)`, which adds the table to a schema that lacks it.

## The routes

The recipe imports the routes of the administration under `/admin/incidents` and those of the status page under
`/status`; change the prefixes there. The administration belongs behind the application's firewall:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/admin/incidents, roles: ROLE_ADMIN }
```

The administration checks the role of `phil_incident_mode.admin.role` itself as well.

## The kit

The pages draw the recipes `Alert`, `Badge`, `Button`, `Field`, `Input`, `NativeSelect`, `Table` and `Textarea`. Install
them from the PHIL UX kit, or from the Shadcn kit of the Symfony UX Toolkit, which have the same names:

```sh
for recipe in alert badge button field input native-select table textarea; do
    php bin/console ux:install "$recipe" --kit=github.com/parler-haut-interagir-librement/ux-kit
done
```

With Tailwind, install the same recipes with `--kit=shadcn`; `phil/ux-kit-shadcn` adds the PHIL recipes that kit lacks.

The recipes are found under the components subdirectory of the `ux_toolkit` configuration. In an environment where
the Toolkit is not enabled, as production often is, repeat it in `phil_incident_mode.kit_subdirectory`.

## The transports

Route the delayed message to a transport that supports delays — Doctrine, Redis, AMQP; not `sync` — and consume the
transport of the recurring sweep, as [Automatic lifting](automatic-lifting.md) shows:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Phil\IncidentMode\Messenger\LiftIncident: async
```

```sh
php bin/console messenger:consume async scheduler_incident_mode
```

## The moderation bundle

`phil/moderation-bundle` declares the services of the library itself, with its own pages, when the library is
installed. An application that registers both bundles gets the services of the library from the bundle registered
last, and each bundle's pages; this bundle is the integration of the library for any application.
