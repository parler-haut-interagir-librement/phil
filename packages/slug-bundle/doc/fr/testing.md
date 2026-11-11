<!-- phil-dev source-sha256=3fe485c73f3f14675eb1e19447f9bb5c041bd862a70ebdb891f5f2fc3028df8f -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `PhilSlugBundle`, `SluggerServices` | Le générateur par défaut, les générateurs nommés et leurs options, les alias par nom d'argument, `Sluggers`, chaque configuration refusée |
| `SlugExtension` | Le générateur par défaut et un générateur nommé, un slug vide quand il n'y a rien à transformer et pour `null`, un nom inconnu |
| Une petite application (fonctionnel) | L'autoconnexion par nom, le décorateur d'un générateur nommé propre à l'application, le filtre dans un gabarit |
| `SlugHistoryKeys` | Chaque portée et chaque sujet acceptés et refusés, à leurs limites et au-delà, l'UTF-8 invalide |
| L'historique dans une application (fonctionnel) | La table dans le schéma de l'ORM, un changement et sa lecture, aucune chaîne après plusieurs changements, un slug de nouveau actuel, un ancien slug repris par un autre sujet, `forget()`, une valeur stockée qui n'est pas un slug, une portée ou un sujet refusé avant toute requête |

```sh
vendor/bin/phpunit packages/slug-bundle/tests
```

Les tests fonctionnels démarrent un noyau et ont besoin de `symfony/framework-bundle`, de `symfony/twig-bundle` et,
pour l'historique, de `doctrine/doctrine-bundle`, installés pour le développement seulement. Le test de l'historique
tourne sur SQLite (un fichier dans le dossier de cache du noyau). Définissez `PHIL_SLUG_TEST_DATABASE_URL` pour le
lancer sur un autre serveur :

```sh
PHIL_SLUG_TEST_DATABASE_URL='mysql://root:secret@127.0.0.1:3306/slugs' vendor/bin/phpunit packages/slug-bundle/tests/Functional
PHIL_SLUG_TEST_DATABASE_URL='postgresql://app:secret@127.0.0.1:5432/app_test?serverVersion=17' vendor/bin/phpunit packages/slug-bundle/tests/Functional
```
