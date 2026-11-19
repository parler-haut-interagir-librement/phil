<!-- phil-dev source-sha256=d5d05040608f900bc141b25a5a6c5321e71803ccf29de734c4547537d21b8da9 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Record`, `Title`, `Copy`, `Loan` | Chaque valeur et son refus, ISBN-10 et ISBN-13, les états d'un prêt et leurs changements, moments en UTC |
| `Catalogue`, `Loans`, `Reminders`, `LoanPolicy` | Chaque méthode sur des dépôts en mémoire : échéances sur l'horloge de la bibliothèque, réservations, sorties, renouvellements, retours, pertes, limites du membre, chaque refus, mouvements de stock, événements, rappels et avis envoyés une seule fois, historique oublié |
| Les adaptateurs | Messenger, le lien avec stockroom sur un vrai `Stockroom` en mémoire et sur les tables de `phil/stockroom-bundle` dans la transaction d'un prêt qui est annulée, le module de vie privée, les commandes, les noms du kit, les liens EasyAdmin, les rôles des pages |
| Le bundle | Ses services et ses options, les passes de compilation — la réserve de `phil/stockroom-bundle` et la transaction du prêt sur une connexion partagée —, l'association |
| Les pages | Un noyau sur SQLite avec Twig, les recettes du kit PHIL UX, des formulaires avec CSRF et Messenger : le catalogue cherché et paginé, un titre, le comptoir, l'administration, les prêts en retard, les prêts du membre, les rappels gérés, un exemplaire lié prêté et retourné avec `phil/stockroom-bundle` sur la même base de données |
| MySQL 8.4 et PostgreSQL 17 | `tests/Integration` : les tables issues de l'association, ce qui est conservé relu en UTC, la recherche et ses décomptes, chaque requête des prêts, les changements en bloc de l'historique, la cascade d'un titre supprimé, le code à barres unique, deux prêts d'un même exemplaire faits en même temps |

Infection maintient le bundle à 100 % des mutants couverts.

```sh
vendor/bin/phpunit packages/library-bundle/tests
```

La suite d'intégration a besoin des serveurs de `phil/dev-tools`, dans Docker ; sans leurs URL, elle est ignorée :

```sh
vendor/bin/phil-dev services up
vendor/bin/phil-dev services test -- packages/library-bundle/tests/Integration
vendor/bin/phil-dev services down
```

Elle supprime et recrée les trois tables du bundle dans la base de données de test.
