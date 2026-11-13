<!-- phil-dev source-sha256=1d3d9afd3d0115ec8b18a449775fb2222736e6fa0e3afb438d5bcc12eb5cb8d5 -->
# Tests

## Les tests du bundle

À la racine du monodépôt :

```sh
vendor/bin/phpunit packages/fonts-bundle/tests
```

`tests/Unit` écrit de petits fichiers de police octet par octet (`Fixture\Fonts` : TrueType, OpenType, collections, WOFF
et WOFF2) dans des répertoires temporaires. Il lit WOFF2 par `str_pad`, qui retourne tel quel le flux du jeu de données
d'essai : les chemins WOFF2 s'exécutent donc avec et sans `ext-brotli`. La médiathèque s'exécute en mémoire, et les
faces TrueType passent par le générateur natif de `phil/paged-media`. `tests/Functional` démarre une petite application
(FrameworkBundle, TwigBundle) et vérifie le catalogue, `fonts_css()` et les commandes. Infection impose au bundle un
score de mutation de 100 % sur le code couvert.

## Tester une application qui l'utilise

Faites pointer `phil_fonts.directory` vers un répertoire de polices d'essai dans l'environnement de test, ou construisez
un `FontCatalogue` sur un répertoire temporaire dans un test unitaire. Pour vérifier qu'une construction refuse une
police non vérifiée, exécutez `phil:fonts:licences` avec `CommandTester` et vérifiez son code de sortie.
