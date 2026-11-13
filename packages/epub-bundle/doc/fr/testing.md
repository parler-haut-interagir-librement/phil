<!-- phil-dev source-sha256=9e85b53609512e4dbe0526bc527b246d7c459869bd3e95cfa239781ef626924c -->
# Tests

## Les tests du bundle

À partir de la racine du monodépôt :

```sh
vendor/bin/phpunit packages/epub-bundle/tests
```

`tests/Unit` écrit des livres et relit leurs archives, les conserve dans des répertoires temporaires et dans une
médiathèque tenue en mémoire, incorpore des fichiers de police écrits octet par octet, et exécute le handler et la
commande avec des stockages qui échouent. `tests/Functional` démarre une application avec FrameworkBundle, Messenger qui
traite les messages immédiatement et `phil/fonts-bundle`, répartit `WriteEpub` vers une source enregistrée par attribut,
et vérifie le livre conservé et l'événement. Infection exige du bundle un score de mutation couvert de 100 %.

## Tester une application qui l'utilise

Dans l'environnement de test, faites pointer `storage.directory` vers un répertoire temporaire et acheminez `WriteEpub`
vers le transport `sync://`, puis lisez l'archive avec `ZipArchive`. Une source de livres est une simple classe :
construisez son livre dans un test unitaire et comparez `Epubs::write()` à un fichier de référence, puisque le même
livre donne les mêmes octets.
