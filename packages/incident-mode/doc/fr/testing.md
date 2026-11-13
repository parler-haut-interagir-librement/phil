<!-- phil-dev source-sha256=74a11ac8e784ac123edc3f5b3094ebac8ea9834368e2776ed2f58b27eb6c29b1 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Domaine | Ce qui est couvert |
|---|---|
| Les valeurs | Les noms de restrictions et leurs bornes, les durées et leurs bornes avec les dépassements, les acteurs et le refus des adresses |
| Les préréglages | Leurs vérifications, ceux qui sont fournis, ceux de l'application ajoutés, remplacés et écartés |
| Les activations | En vigueur, échues, levées, prolongées, ce qu'elles suspendent, chaque refus |
| `IncidentMode` | Le déclenchement dans les limites du préréglage, la vérification, la prolongation, la levée à la main, l'échéance inscrite une seule fois, le balayage, le journal, les événements, un magasin qui a perdu la course |
| Les magasins | Une seule suite pour le magasin en mémoire, le magasin DBAL sur SQLite, MySQL 8.4 et PostgreSQL 17 : ordre, limites, mises à jour conditionnelles, UTC, acteurs retrouvés et oubliés, lignes qui ne se lisent plus |
| Messenger | Le délai du message, le handler qui lève une fois l'échéance atteinte, le balayage |

Les tests d'intégration lisent `PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL` et sont ignorés sans elles. Dans le
monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## Banc d'essai

`tests/Benchmark/IncidentModeBench.php` exécute la vérification que fait une page, sur SQLite en mémoire avec un journal
de 5 000 incidents passés. Sur un portable récent :

| Sujet | Temps |
|---|---|
| Aucun incident en vigueur | environ 13 µs |
| Deux incidents en vigueur | environ 35 µs |

```sh
composer bench -- packages/incident-mode/tests/Benchmark
```

## Tester une application

Donnez à `IncidentMode` un `InMemoryActivationStore` et une `MockClock`, déclenchez un incident, vérifiez que la page
refuse ce qu'il suspend, puis avancez l'horloge au-delà de la fin et vérifiez que la page est de nouveau ouverte.
