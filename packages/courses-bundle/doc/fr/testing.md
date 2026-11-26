<!-- phil-dev source-sha256=6c521130396168afae84daca266be7420062a3aace95c5d1a52d6a4ac430ceb5 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | Ce qu'elle couvre |
|---|---|
| `tests/Unit` | Le domaine, les points d'entrée, l'import et ses blocs, les achèvements, les règles de cmi5, les énoncés `satisfied`, l'environnement d'exécution SCORM, ses tentatives, jetons, point de terminaison, cadre et module de vie privée, la révision d'une tentative terminée, les limites de temps, le module de vie privée de cmi5, les entrepôts, la mise à niveau des tables, sur SQLite en mémoire, et l'entrepôt de traces d'apprentissage distant sur un client simulé |
| `tests/Functional` | Le bundle dans une petite application : les pages, la page d'administration, l'origine du contenu, l'URL de récupération, le point de terminaison xAPI, les commandes, et un paquet SCORM 1.2 et un paquet SCORM 2004 importés, lancés et validés, suspendus, repris et recommencés, révisés une fois terminés, et une limite de temps appliquée |
| `tests/Integration` | Les tables, leur mise à niveau et les requêtes sur MySQL 8.4 et PostgreSQL 17 — les tentatives SCORM et leurs clés comparées octet par octet, entre autres, leur temps et leur révision, les enregistrements cmi5 d'un apprenant exportés et effacés —, et un énoncé envoyé à un vrai entrepôt de traces d'apprentissage |

## Les scripts

Les scripts de l'environnement d'exécution SCORM sont testés avec le lanceur de tests propre à Node.js, Stimulus remplacé par une doublure :

```sh
node --import ./tests/js/register.mjs --test 'tests/js/*.test.mjs'
```

`cmi.test.mjs` exécute les appels de `tests/js/fixture/vectors.json` contre l'API dans le code du navigateur, et
`BrowserParityTest` exécute les mêmes appels contre le modèle de données de phil/elearning-packages, avec les descriptions
de `tests/js/fixture/model-*.json` tenues pour ce que le serveur remet au navigateur : les deux répondent de la même façon à
chaque appel. La fixture est écrite à partir des réponses du serveur. `tests/js/fixture/review.json` fait de même pour une révision : les valeurs qu'une révision remet au contenu, que `ScormReviewTest` tient pour ce que le serveur donne pour une tentative terminée, et les appels du contenu en mode révision, auxquels le serveur et le navigateur répondent de la même façon.

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
l'enregistrement et la session de son lancement, ou refusé. La cible de fuzzing `scorm-commit` donne à la lecture d'une
validation et au modèle de données de chaque version des corps que personne n'a écrits : un corps est lu ou refusé, et ce
qui est conservé reste dans sa limite. La cible de fuzzing `catalogue-query` donne à la lecture d'une page de catalogue des
chaînes de requête que personne n'a écrites : quoi qu'elles contiennent, la requête lue reste dans ses limites, et la
chaîne de requête qu'elle écrit se lit comme la même requête. Les lecteurs des paquets et le modèle de données sont
soumis au fuzzing dans phil/elearning-packages.

```sh
composer fuzz -- cmi5-statement scorm-commit catalogue-query
```

## Bancs d'essai

`tests/Benchmark/CoursesBench.php` mesure la vérification d'un énoncé, le placement des activités d'un cours de cent
activités pour un apprenant, la validation d'une session SCORM dont la tentative contient cinquante interactions, et une page d'un catalogue de cinq
cents cours filtrés par mots et triés par titre, avec où en est l'apprenant. Les lecteurs des paquets sont mesurés dans
phil/elearning-packages.

## Tester le code qui utilise le bundle

`CourseTables::configure()` crée les tables sur toute connexion DBAL, SQLite en mémoire comprise. Une `MockClock` comme
service `clock` fixe les moments que lisent les ouvertures et les sessions.
