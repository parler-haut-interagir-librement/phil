<!-- phil-dev source-sha256=8acdadd96b7d4b92001a10768b724bb039e3ab5fa7ab16e232fd62a47cb2ee8c -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| Horodatage et auteur | Des entités horodatées et nommées à leur persistance et à leur modification, en UTC, personne de connu, des valeurs données gardées, des entités sans comportement laissées en paix |
| Suppression douce | Une suppression gardée comme date, les données d'origine que garde l'unité de travail, le filtre actif et inactif, la restauration, une seconde suppression qui efface pour de bon, une entité qui a son propre `deletedAt` laissée visible |
| Désactivation | Éteindre et rallumer, la date gardée accordée, des dates données gardées, le filtre actif et inactif, une entité sans colonne à elle laissée visible |
| Propriétaire | Les identifiants refusés et acceptés, le propriétaire donné à la persistance, gardé à la modification, transféré, personne de connu, l'effaceur sur les tables et les sous-classes |
| Publication | La machine à états avec ses dates, une transition refusée, une date donnée gardée, d'autres sujets et d'autres places laissés en paix |
| Slug | Fait une fois, numéroté, des slugs donnés rendus uniques, refait, les lignes cachées par les filtres comptées, coupé à la colonne, les tables héritées, chaque numéro pris, le dernier numéro essayé |
| Versions | La nouvelle version après un gel, ce qui n'en crée pas, le gel de ce qui est en attente, la liste et sa limite, le retour en arrière, le droit de le faire, un numéro inconnu, un état non mappé |
| Identifiant daté | Un UUID v7 de l'heure de l'horloge, un identifiant donné gardé, tout le reste refusé, l'ordre de création |
| Positions | La fin du groupe pour un enregistrement sans position, des positions données gardées, des groupes de colonnes, d'énumérations et de relations, les déplacements avant, après, en tête et à la fin, les lignes verrouillées avant chaque déplacement, les enregistrements chargés qui suivent, les groupes refusés |
| Traduction | Des lignes créées vides, jamais recopiées, lues entières le long d'une chaîne, les lignes en attente et retirées, les locales d'un enregistrement, une liste en une requête par classe et par tranche, l'index unique, les slugs et les versions par locale, les mappages et les traductions refusés |
| Arbres | Des chemins d'identifiants venus de la base et de l'appelant, les racines, les enfants, les descendants, les ancêtres, la hiérarchie et son ordre, des branches déplacées avec des descendants chargés ou non, des déplacements en un seul vidage, les jokers de LIKE dans les identifiants, des nœuds sans chemin, les places refusées |

```sh
vendor/bin/phpunit packages/doctrine-behaviours/tests
```

## Vrais serveurs

`tests/Integration` exécute ce qui dépend d'une base de données, la graphie du vrai, le `IN` des slugs, l'état en JSON,
les clés `guid`, l'effaceur, les traductions lues le long d'une chaîne et gardées une par locale, et les déplacements
qui s'attendent dans un groupe mais pas d'un groupe à l'autre, sur MySQL et PostgreSQL, quand `PHIL_TEST_MYSQL_URL` et
`PHIL_TEST_POSTGRESQL_URL` sont définies. Sans elles, les tests sont ignorés.

```sh
vendor/bin/phil-dev services up
eval "$(vendor/bin/phil-dev services env)"
vendor/bin/phpunit packages/doctrine-behaviours/tests/Integration
```

## Bancs d'essai

Les sujets phpbench sous `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. À la racine du
monodépôt :

```sh
composer bench -- packages/doctrine-behaviours/tests/Benchmark
```
