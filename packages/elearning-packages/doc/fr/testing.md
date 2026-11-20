<!-- phil-dev source-sha256=909f2435942e67e97a2f9f8ff16aff0084f5070984805d916989df724660684d -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

`tests/Unit` couvre l'archive, les chemins, la lecture XML sûre, les limites, les lecteurs de SCORM, de cmi5 et de xAPI,
`PackageReader`, les valeurs, le modèle de données d'exécution des deux versions — chaque règle de lecture et d'écriture,
les codes d'erreur, l'API et ses états, les validations, les statuts, les notes et les temps —, et le séquencement : la
lecture de chaque élément, chaque demande de navigation, chaque famille de règles et de conditions, le cumul et ses
considérations, les objectifs globaux, la sélection et le réordonnancement, l'état et ses bornes, et les prérequis de
SCORM 1.2. Les tests de séquencement parcourent des manifestes écrits à la main que construit
`tests/Fixture/SequencingManifests.php` ; un test de propriété cumule soixante arbres aléatoires de résultats aléatoires
et les compare aux valeurs par défaut calculées à part, et aux mêmes arbres dont les enfants sont inversés. Les paquets
que lisent les tests — un manifeste SCORM 1.2, un manifeste SCORM 2004 avec séquencement, une structure de cours cmi5 —
sont écrits pour les tests dans `tests/Fixture/Packages.php`, et compressés en ZIP dans le répertoire temporaire ;
`tests/Fixture/truncated.zip` est une archive dont les données s'arrêtent avant ce que dit son entrée, ce que l'extension
zip ne peut pas écrire. Dans le monodépôt, Infection tient le paquet à 100 % des mutants couverts.

## Fuzzing

Sept cibles de fuzzing donnent aux lecteurs, au modèle de données et au séquenceur des entrées que personne n'a écrites,
à partir des germes de `tests/Fuzz/corpus/<name>` :

| Cible | Lit |
|---|---|
| `scorm-manifest` | `imsmanifest.xml` : lu en un manifeste dont l'organisation par défaut a des éléments et dont les fichiers de lancement restent dans le paquet, ou refusé |
| `cmi5-structure` | `cmi5.xml` : lu en un cours d'unités aux identifiants uniques et aux scores de maîtrise de 0 à 1, ou refusé |
| `zip-package` | Une archive ZIP : refusée, ou ouverte avec des chemins à l'intérieur et des fichiers lus dans les limites |
| `cmi-commit` | Une validation en JSON, appliquée aux données d'une tentative de chaque version : conservée dans sa limite, rechargée, terminée |
| `xapi-package` | `tincan.xml` : lu en un cours dont les activités ont des IRI uniques et dont les fichiers de lancement restent dans le paquet, ou refusé |
| `prerequisites` | Une expression de prérequis de SCORM 1.2 : analysée en des prérequis qui répondent pour chaque statut et ne nomment que des identifiants de l'expression, ou refusée |
| `sequencing` | `imsmanifest.xml` : lu en un arbre d'activités, ou refusé ; puis parcouru par chaque demande de navigation, chaque état relu à partir de ses valeurs simples |

Chaque cible échoue devant tout autre résultat qu'une lecture ou un `PackageRefused`, avertissements compris ;
`cmi-commit`, devant toute exception.

```sh
composer fuzz -- scorm-manifest cmi5-structure zip-package cmi-commit xapi-package prerequisites sequencing
```

## Bancs d'essai

`tests/Benchmark/ElearningPackagesBench.php` mesure la lecture d'un manifeste SCORM et d'une structure de cours cmi5, d'un
manifeste de mille éléments, la vérification d'une archive de mille entrées, la lecture d'un paquet entier dont les
fichiers de lancement sont vérifiés, l'application d'une validation de SCORM 2004, le chargement, l'extension et la fin
des données d'une tentative de 250 interactions, la lecture d'un paquet xAPI de mille activités et du séquencement de
mille activités, et, sur un arbre de mille activités dont l'état est relu à partir de ses valeurs simples, un rapport
cumulé et un passage à la suite.

## Tester le code qui utilise la bibliothèque

Chaque valeur a un constructeur public, et `PackageReader::read()` accepte toute `PackageArchive` : un test lui donne une
archive en mémoire, ses fichiers par nom, sans écrire de fichier ZIP.
