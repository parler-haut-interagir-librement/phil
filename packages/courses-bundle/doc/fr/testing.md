<!-- phil-dev source-sha256=89f05604af0142473a7bc74fae646e9b143548dbf85fd7a34b1c38d65cf4c55a -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | Ce qu'elle couvre |
|---|---|
| `tests/Unit` | Le domaine, les points d'entrée, l'import et ses blocs, les achèvements, les règles de cmi5, les énoncés `satisfied`, les entrepôts, la mise à niveau des tables, sur SQLite en mémoire, et l'entrepôt de traces d'apprentissage distant sur un client simulé |
| `tests/Functional` | Le bundle dans une petite application : les pages, la page d'administration, l'origine du contenu, l'URL de récupération, le point de terminaison xAPI et les commandes |
| `tests/Integration` | Les tables, leur mise à niveau et les requêtes sur MySQL 8.4 et PostgreSQL 17, et un énoncé envoyé à un vrai entrepôt de traces d'apprentissage |

Les paquets que les tests importent — un manifeste SCORM 1.2, un manifeste SCORM 2004 avec séquencement, une structure de
cours cmi5 — sont écrits pour les tests, dans `tests/Unit/Fixture/Packages.php`, et compressés dans le répertoire
temporaire. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

Les tests d'intégration lisent l'URL de leur serveur dans l'environnement et sont ignorés sans elle :
`PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL`, comme
`mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4`.

Le test de l'entrepôt de traces d'apprentissage distant lit son point de terminaison dans `PHIL_TEST_LRS_URL` — une
adresse `https` sur un hôte public, comme un locataire d'essai d'un entrepôt hébergé —, la clé et le secret de
l'authentification HTTP Basic dans `PHIL_TEST_LRS_USERNAME` et `PHIL_TEST_LRS_PASSWORD`, et la version de xAPI dans
`PHIL_TEST_LRS_VERSION`, `1.0.3` par défaut. Il envoie un énoncé deux fois, et est ignoré sans le point de terminaison :

```sh
PHIL_TEST_LRS_URL=https://lrs.example.org/xapi/ PHIL_TEST_LRS_USERNAME=key PHIL_TEST_LRS_PASSWORD=secret \
    vendor/bin/phpunit --no-configuration tests/Integration/RemoteLrsTest.php
```

Les tests fonctionnels remplacent Symfony Security par un vérificateur d'autorisation qui accorde les rôles de l'en-tête
`X-Roles`, et enveloppent l'entrepôt intégré au bundle dans un entrepôt qu'ils font refuser les énoncés, comme le
ferait un entrepôt distant hors d'atteinte.

## Fuzzing

La cible de fuzzing `cmi5-statement` donne aux vérifications du point de terminaison xAPI des énoncés que personne n'a
écrits, à partir des germes de `tests/Fuzz/corpus/cmi5-statement` : un énoncé est accepté seulement pour l'acteur,
l'enregistrement et la session de son lancement, ou refusé. Les lecteurs des paquets sont soumis au fuzzing dans
phil/elearning-packages.

```sh
composer fuzz -- cmi5-statement
```

## Bancs d'essai

`tests/Benchmark/CoursesBench.php` mesure la vérification d'un énoncé et le placement des activités d'un cours de cent
activités pour un apprenant. Les lecteurs des paquets sont mesurés dans phil/elearning-packages.

## Tester le code qui utilise le bundle

`CourseTables::configure()` crée les tables sur toute connexion DBAL, SQLite en mémoire comprise. Une `MockClock` comme
service `clock` fixe les moments que lisent les ouvertures et les sessions.
