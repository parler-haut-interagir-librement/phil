<!-- phil-dev source-sha256=d0ef11e5d4828453b66d448e0dc1e25c027ecd9454ed7b30563d90fbc629e054 -->
# Tests

## Les tests du bundle

La suite `tests/Unit` teste :

| Domaine | Ce qui est testé |
|---|---|
| `ProcessCommandRunner` | les outils trouvés dans le `PATH` ou là où le dit la configuration, un outil absent nommé avec la clé de configuration ; un environnement, un fichier d'entrée et la sortie ; un échec avec sa sortie d'erreur ; un délai dépassé |
| `DatabaseClients` | le client de chaque plateforme à partir de ses paramètres, sans se connecter ; une base SQLite en mémoire et une autre plateforme refusées |
| `Console` | les trois commandes sur un fichier SQLite avec le vrai `sqlite3` : un jeu créé, puis les jeux expirés supprimés, les étiquettes et leur refus ; chaque refus d'une restauration, la production avec et sans la bonne autorisation, le nom demandé de façon interactive, la sauvegarde faite d'abord et son contenu, les trois plus récentes gardées, l'élagage des médias sur demande, un déchiffrement raté ; les tests de restauration sur SQLite et sur un client MySQL enregistré, avec et sans base de travail |
| `PhilBackupBundle` | l'extension chargée sur un `ContainerBuilder` non compilé : une configuration sans clé refusée sauf si elle est permise, les valeurs par défaut, chaque clé qui atteint son service, chaque erreur à la construction ; un conteneur compilé qui construit le client sans se connecter |

À partir de la racine du monodépôt :

```bash
vendor/bin/phpunit packages/backup-bundle/tests
```

Les tests de console exigent l'outil en ligne de commande `sqlite3` dans le `PATH`. Le bundle est tenu à un score de
mutation couvert de 100 % par Infection.

## Tester votre configuration

Démarrez le noyau dans l'environnement de test et exécutez `phil:backup:create`, puis `phil:backup:verify`, sur une base
SQLite : les deux réussissent lorsque la configuration, la clé et les répertoires de médias sont corrects. Pour tester
une restauration, passez `--confirm --database=<name>` et exécutez la console sans interaction.
