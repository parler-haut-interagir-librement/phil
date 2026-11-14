<!-- phil-dev source-sha256=63ea074e33e47df734df6821406011e713b56edea9997d70ba0020a8641fac25 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `PhilMigrationBundle`, `AnnouncementServices` | Sans canaux, seulement les annonces ; les canaux sous forme de définitions `Channel` avec leurs valeurs par défaut, l'éditeur de chatter, le journaliseur ; ce que la configuration refuse |
| `AnnouncingMigrationsPass` | Une fabrique ou un migrateur fourni par l'application décoré, ceux que fabrique Doctrine Migrations enveloppés, les appels de méthode qui ne donnent rien ignorés, les décorations après les autres, les migrations `Announcing` du conteneur munies des annonces et les autres laissées telles quelles, aucun migrateur sans canaux, rien sans DoctrineMigrationsBundle, la passe enregistrée |
| Une application complète | FrameworkBundle, DoctrineBundle sur SQLite en mémoire et DoctrineMigrationsBundle : une exécution à blanc n'annonce rien, une exécution de deux migrations d'un dossier et d'une du conteneur donne un message par canal |

```sh
vendor/bin/phpunit packages/migration-bundle/tests
```
