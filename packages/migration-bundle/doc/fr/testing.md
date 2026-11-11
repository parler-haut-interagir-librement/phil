<!-- phil-dev source-sha256=2a0688f36686a526eb6a2e9fe70f67856a395947950f8037610757d888f91d83 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `PhilMigrationBundle`, `AnnouncementServices` | Sans canaux, seulement les annonces ; les canaux sous forme de définitions `Channel` avec leurs valeurs par défaut, l'éditeur de chatter, le journaliseur ; ce que la configuration refuse |
| `AnnouncingMigrationsPass` | Une fabrique ou un migrateur fourni par l'application décoré, ceux que fabrique Doctrine Migrations enveloppés, les appels de méthode qui ne donnent rien ignorés, aucun migrateur sans canaux, rien sans DoctrineMigrationsBundle, la passe enregistrée |
| Une application complète | FrameworkBundle, DoctrineBundle sur SQLite en mémoire et DoctrineMigrationsBundle : une exécution à blanc n'annonce rien, une exécution de deux migrations donne un message par canal |

```sh
vendor/bin/phpunit packages/migration-bundle/tests
```
