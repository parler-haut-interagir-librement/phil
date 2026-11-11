<!-- phil-dev source-sha256=ad82576875ed9d48e48c9a531a6eb8d2bb991cb895fe73e68f2b9cb12601d8cd -->
# Intégration

Les décorateurs se branchent dans le `DependencyFactory` de Doctrine Migrations. À l'intérieur d'une définition,
demander à la fabrique le service en cours de définition donne celui que fabrique Doctrine Migrations, que le décorateur
enveloppe :

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

Une application qui fournit déjà son propre `MigrationFactory` enveloppe plutôt celui-là, au lieu de le demander à la
fabrique de dépendances.

Les deux décorateurs partagent le même `Announcements`. La bibliothèque n'écoute aucun événement de Doctrine
Migrations : sans l'ORM, Doctrine Migrations les répartit vers un gestionnaire d'événements que personne ne peut
atteindre, alors que le migrateur voit chaque exécution, quelle que soit l'application.

Un `Publisher` propre à l'application remplace `ChatterPublisher` pour envoyer n'importe où ailleurs.
