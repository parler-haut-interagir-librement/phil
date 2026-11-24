# Installation

```sh
composer require phil/virtual-classroom-bundle
```

With Symfony Flex, the recipe enables the bundle, writes `config/packages/phil_virtual_classroom.yaml` with a
BigBlueButton server read from `BBB_URL` and `BBB_SECRET`, and `config/routes/phil_virtual_classroom.yaml`, which
imports the routes under `/virtual-classroom`. In development, `allow_localhost` is on; in tests, the rooms are kept in
memory.

`BBB_SECRET` is written empty: give it the secret the server shares — `bbb-conf --secret` prints it — out of the
repository, in `.env.local`, a Symfony secret or the environment of the server. The service of a server refuses a
secret shorter than 16 bytes — an empty one among them — and one that begins with `change-me` or `changeme`, the
placeholders examples use (`ServerSecret`), when it is first built.

Without Flex, enable the bundle and import its routes:

```php
// config/bundles.php
Phil\VirtualClassroomBundle\PhilVirtualClassroomBundle::class => ['all' => true],
```

```yaml
# config/routes/phil_virtual_classroom.yaml
phil_virtual_classroom:
    resource: '@PhilVirtualClassroomBundle/config/routes.php'
    prefix: /virtual-classroom
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later, Doctrine DBAL 4.5 or later through DoctrineBundle, and Symfony's HTTP client.
The console, Messenger, Security, phil/courses-bundle and phil/privacy-bundle are optional: each part is registered
when its package is installed and, for the two PHIL bundles, enabled.

## The tables

The bundle keeps three tables, `phil_virtual_classroom_room`, `_stay` and `_recording`. With the ORM,
`ClassroomSchemaListener` adds them to the schema it generates, and `doctrine:migrations:diff` writes the migration.
Without the ORM, add them in a migration:

```php
$schema = ClassroomTables::for($connection->getDatabasePlatform())->configure($schema);
```

## The webhook

The servers call the application back: the routes of the webhook must be reachable from them, over https, under the
public host of the application. Set the router's default URI, `framework.router.default_uri`, so that the addresses
written from the console or a worker name that host.
