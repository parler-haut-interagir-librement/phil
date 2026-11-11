# Integration

The decorators plug into the `DependencyFactory` of Doctrine Migrations. Inside a definition, asking the factory
for the service being defined gives the one Doctrine Migrations makes, which the decorator wraps:

```php
$announcements = new Announcements();
$announcer = new Announcer([new Channel('discord_releases', 'en')], new ChatterPublisher($chatter), $logger);

$dependencyFactory->setDefinition(
    MigrationFactory::class,
    static fn (DependencyFactory $factory): MigrationFactory => new AnnouncingMigrationFactory($factory->getMigrationFactory(), $announcements),
);
$dependencyFactory->setDefinition(
    Migrator::class,
    static fn (DependencyFactory $factory): Migrator => new AnnouncingMigrator($factory->getMigrator(), $announcements, $announcer),
);
```

An application that already gives its own `MigrationFactory` wraps that one instead of asking the dependency
factory for it.

Both decorators share the same `Announcements`. The library listens to no event of Doctrine Migrations: without
the ORM, Doctrine Migrations dispatches them to an event manager no one can reach, while the migrator sees every run
whatever the application.

A `Publisher` of the application's own replaces `ChatterPublisher` to send anywhere else.
