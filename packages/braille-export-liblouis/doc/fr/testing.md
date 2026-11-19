<!-- phil-dev source-sha256=e574d12f9b33eda1e4183d6c632cddd801101da514971234804a7d55690e081e -->
# Tests

| Zone | Ce qui est couvert |
|---|---|
| `LiblouisTranslator` | Les arguments, l'entrée à raison d'un texte par ligne, la réponse en braille Unicode, les textes vides omis, les caractères de contrôle, les textes longs en morceaux, le chemin des tables, la table d'affichage, un programme et un chemin manquants, une table manquante, un code de sortie et sa sortie d'erreur, la borne de la sortie d'erreur, le délai, les limites d'entrée et de sortie, une réponse du mauvais nombre ou qui n'est pas du braille, la version |
| `LiblouisTables` | Les valeurs par défaut, une région qui se rabat sur sa langue, une copie avec une table, chaque nom et chaque étiquette refusés |

Les tests unitaires, sous `tests/Unit`, exécutent un script d'interpréteur qui remplace `lou_translate` et garde ses
arguments, son entrée et son chemin des tables. Ils n'ont besoin d'aucun liblouis, mais d'un interpréteur POSIX :

```sh
vendor/bin/phpunit packages/braille-export-liblouis/tests/Unit
```

La suite sous `tests/Integration`, du groupe `liblouis`, exécute le vrai programme trouvé dans le `PATH`, et est ignorée
sans lui. Elle lit la version, traduit avec chaque table par défaut, vérifie que l'anglais abrégé est plus court que
l'anglais intégral, garde l'ordre d'un lot avec un texte long, met en page un document français et l'écrit en BRF, et
signale une table manquante :

```sh
vendor/bin/phpunit packages/braille-export-liblouis/tests/Integration
```

Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.
