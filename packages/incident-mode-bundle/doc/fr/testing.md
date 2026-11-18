<!-- phil-dev source-sha256=39cfc88f6c0882e50c098297e29783f99c79d7f19b9a804c8df245556c981ae6 -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | Ce qui est couvert |
|---|---|
| `tests/Unit` | La configuration et ses refus, chaque service et ses arguments, la passe du compilateur, `DurationInput`, `Checkpoint` et ses lectures, les mots en anglais et en français, les commandes, l'attribut, l'écouteur, l'option des formulaires, la planification, l'audit, la vie privée |
| `tests/Functional` | Une application sur SQLite : l'administration et ses refus, le rôle, le jeton CSRF, les pages du journal, les points de contrôle d'un contrôleur, d'une classe, d'une route, d'un chemin de code et d'un formulaire, les membres établis, les pages de refus, d'état et d'avis, la bannière, le message différé, la planification, les pages dessinées avec le kit PHIL UX et avec le kit Shadcn |
| `tests/Integration` | La même application sur MySQL 8.4 et PostgreSQL 17 : un incident exécuté depuis l'administration, refusé, prolongé et consigné une seule fois à sa fin, les moments en UTC |

Les tests d'intégration lisent `PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL` et sont sautés sans elles ; dans le
monodépôt, `phil-dev services up` démarre les deux serveurs. L'application fonctionnelle garde ses caches et son fichier
SQLite sous le répertoire temporaire du système.

## Fuzzing

`tests/Fuzz/duration-input.php` lit comme des durées des textes que personne n'a écrits : chacun donne une durée dans
les bornes de la bibliothèque ou `InvalidIncident`, jamais une autre erreur ni un avertissement, et une durée se relit à
partir de son propre texte.

```sh
composer fuzz -- duration-input
```

## Banc d'essai

`tests/Benchmark/CheckpointBench.php` exécute le point de contrôle d'une inscription et la bannière d'une page, sur
SQLite en mémoire avec un journal de 5 000 incidents passés. Sur un portable récent :

| Sujet | Aucun incident en vigueur | Deux incidents en vigueur |
|---|---|---|
| Le point de contrôle d'une inscription | environ 14 µs | environ 35 µs |
| La bannière d'un visiteur | environ 13 µs | environ 48 µs |

```sh
composer bench -- packages/incident-mode-bundle/tests/Benchmark
```

## Tester une application

Démarrez l'application avec une `MockClock` comme service `clock`, démarrez un incident par `IncidentMode`, vérifiez que
la page refuse ce qu'il suspend avec le statut 503 et `Retry-After`, puis avancez l'horloge au-delà de la fin et
vérifiez que la page est de nouveau ouverte. Pour tester une page qui épargne les membres établis, donnez à
`established_members` un service du test qui détient les identifiants que vous choisissez.
