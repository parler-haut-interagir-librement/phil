<!-- phil-dev source-sha256=78b09766ad081d1d6b0fcf185e1c6db13704e3e1dbfa2a70464837d0c13b1b44 -->
# Tests

Les tests sont des tests PHPUnit, lancés depuis la racine du monodépôt par `composer test`, ou seuls :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

- `tests/Unit` : la salle, `Classrooms` contre deux faux serveurs et un magasin en mémoire, la preuve du webhook, les
  contrôleurs, les commandes, les adaptateurs de Security, de Routing et de phil/privacy-bundle, le type d'activité, le
  câblage de chaque option ; les magasins contre `StoreTestCase`, en mémoire et sur SQLite.
- `tests/Functional` : une application avec FrameworkBundle, DoctrineBundle sur SQLite, SecurityBundle, TwigBundle,
  phil/courses-bundle et phil/privacy-bundle, un serveur BigBlueButton derrière un client HTTP scénarisé et un serveur
  Jitsi : rejoindre, le webhook d'une salle et d'un compte, un cours en direct complété par son assiduité.
- `tests/Integration` : le magasin DBAL sur MySQL 8.4 et PostgreSQL 17, lus depuis `PHIL_TEST_MYSQL_URL` et
  `PHIL_TEST_POSTGRESQL_URL` ; ignorés sans eux. `composer test:services` démarre les serveurs.

## Banc d'essai

`tests/Benchmark/AttendanceBench.php`, lancé par `composer bench` : une notification de 600 événements appliquée aux
séjours, et le rapport d'assiduité de 300 participants, depuis le magasin en mémoire.
