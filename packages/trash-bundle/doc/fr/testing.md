<!-- phil-dev source-sha256=3ddf191508d83abbe9aadc411247e9ce28a0aea25dd9da66ab473979a3a36e7e -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `TrashItem`, `ResourceReference`, les exceptions | Chaque vérification et chaque borne, les heures en UTC, un parent avec un deux-points dans son identifiant, le mapping |
| `Trash` | La mise à la corbeille et la restauration, un parent disparu, la conservation par type, la purge dans l'ordre, les événements, sans horloge ni répartiteur |
| `PurgeTrashCommand` | Les limites, les limites refusées |
| `PhilTrashBundle` | Les services, l'autoconfiguration, la configuration, le mapping |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, une entité `Article` et son
gestionnaire : `TrashBundleTest` met à la corbeille, liste, restaure sous un autre parent, puis purge une fois qu'une
horloge avancée dépasse la durée de conservation.

```sh
vendor/bin/phpunit packages/trash-bundle/tests
```
