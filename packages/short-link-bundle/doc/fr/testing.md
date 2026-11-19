<!-- phil-dev source-sha256=8141d2b63079f12bd60aeb56018c60f2dff19b297c99839426bb4025d8e6fe23 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : le codec contre les
identifiants que liste la spécification Sqids, les liens et leurs conditions, le classeur des visites sur les `User-Agent` de navigateurs,
d'aperçus de liens et de robots, les services sur un dépôt en mémoire, l'écouteur, les adaptateurs, et le dépôt Doctrine sur SQLite en mémoire. La suite fonctionnelle, sous
`tests/Functional`, démarre une petite application avec Doctrine sur SQLite, Twig et la sécurité de Symfony, et
parcourt les domaines courts et l'administration. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants
couverts.

Un test qui a besoin d'un vrai serveur lit son URL dans une variable d'environnement et est ignoré sans elle :
`PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL`. La suite d'intégration, sous `tests/Integration`, crée la table à
partir du mappage sur chaque serveur, relit en UTC ce qui est conservé, vérifie l'unicité du code, le compte d'une
visite selon les conditions du lien, les visites de machines comptées à part et la recherche, et lance des requêtes dans des processus séparés qui se disputent
les dernières visites d'un lien.

## Les cibles de fuzzing

`tests/Fuzz/sqid-decode.php` donne au codec des identifiants que personne n'a écrits : tout ce qu'il lit se décode en
entiers non négatifs ou en rien, sans jamais lancer d'exception ni d'avertissement, et les nombres décodés reviennent de
leur propre identifiant. `tests/Fuzz/visitor-classify.php` donne au classeur des en-têtes que personne n'a écrits : il
répond toujours une classe, la même pour les mêmes en-têtes, et une prélecture seulement quand une intention en nomme
une. Le monodépôt les lance avec `composer fuzz`, à partir des germes de `tests/Fuzz/corpus/<cible>`.

## Le banc d'essai

`tests/Benchmark/KnownVisitorClassifierBench.php` mesure la classification que chaque visite traverse : un navigateur,
la réponse la plus lente, comparé à tous les motifs, prend quelques microsecondes ; un en-tête de 8 Kio est coupé avant
d'être lu.
