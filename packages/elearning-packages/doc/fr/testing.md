<!-- phil-dev source-sha256=d35a0760717232d6a90cb15faee8bb9046f582b81f0b7b2ed07d0c57c87c7073 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

`tests/Unit` couvre l'archive, les chemins, la lecture XML sûre, les limites, les deux lecteurs, `PackageReader` et les
valeurs. Les paquets que lisent les tests — un manifeste SCORM 1.2, un manifeste SCORM 2004 avec séquencement, une
structure de cours cmi5 — sont écrits pour les tests dans `tests/Fixture/Packages.php`, et compressés en ZIP dans le
répertoire temporaire ; `tests/Fixture/truncated.zip` est une archive dont les données s'arrêtent avant ce que dit son
entrée, ce que l'extension zip ne peut pas écrire. Dans le monodépôt, Infection tient le paquet à 100 % des mutants
couverts.

## Fuzzing

Trois cibles de fuzzing donnent aux lecteurs des entrées que personne n'a écrites, à partir des germes de
`tests/Fuzz/corpus/<name>` :

| Cible | Lit |
|---|---|
| `scorm-manifest` | `imsmanifest.xml` : lu en un manifeste dont l'organisation par défaut a des éléments et dont les fichiers de lancement restent dans le paquet, ou refusé |
| `cmi5-structure` | `cmi5.xml` : lu en un cours d'unités aux identifiants uniques et aux scores de maîtrise de 0 à 1, ou refusé |
| `zip-package` | Une archive ZIP : refusée, ou ouverte avec des chemins à l'intérieur et des fichiers lus dans les limites |

Chaque cible échoue devant tout autre résultat qu'une lecture ou un `PackageRefused`, avertissements compris.

```sh
composer fuzz -- scorm-manifest cmi5-structure zip-package
```

## Bancs d'essai

`tests/Benchmark/ElearningPackagesBench.php` mesure la lecture d'un manifeste SCORM et d'une structure de cours cmi5, d'un
manifeste de mille éléments, la vérification d'une archive de mille entrées, et la lecture d'un paquet entier dont les
fichiers de lancement sont vérifiés.

## Tester le code qui utilise la bibliothèque

Chaque valeur a un constructeur public, et `PackageReader::read()` accepte toute `PackageArchive` : un test lui donne une
archive en mémoire, ses fichiers par nom, sans écrire de fichier ZIP.
