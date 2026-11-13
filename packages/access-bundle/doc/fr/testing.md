<!-- phil-dev source-sha256=c62f2a215c10a0298192231fd4784e11a9b43a691e252712898dc61f4160ccd4 -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : les services de la
couche application sur un magasin en mémoire et une horloge simulée, les voters, le contrôleur, la configuration et les
passes du compilateur. La suite fonctionnelle, sous `tests/Functional`, démarre une petite application — FrameworkBundle,
SecurityBundle, TwigBundle, Symfony UX Twig Component avec les recettes du kit — et dessine la bannière et le résumé,
puis démarre et arrête « voir comme ce membre » par ses formulaires. Dans le monodépôt, Infection maintient le paquet à
100 % des mutants couverts.

Le magasin DBAL est testé sur MySQL 8.4 et PostgreSQL 17 par `phil/resource-permissions`.

## Tester l'accès d'une application

Réglez `phil_access.store: memory` dans l'environnement de test, et une `MockClock` comme service `clock`. Écrivez le
cas avec `Items`, `Sharing`, `Spaces` et `AccessGroups`, connectez un utilisateur, puis vérifiez avec `is_granted()` ou
sur la page. Avancer l'horloge au-delà d'une fin teste un accès qui se termine de lui-même.
