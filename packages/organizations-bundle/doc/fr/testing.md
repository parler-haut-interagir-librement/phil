<!-- phil-dev source-sha256=1b7f41eaed648f8f0a0990e2e66f01f325d2b2bebf6b25bf8cd9cd1508e9a219 -->
# Tests

## Les tests du bundle

La suite `tests/Unit` teste les identifiants, les noms, les rôles, les domaines et les chemins d'unité ainsi que leurs
règles, le modèle et les états d'une invitation, les catalogues de rôles, `Organizations`, `Members`, `Teams`, `Units`,
`UnitAccess`, `UnitTree`, `Invitations`, `DomainJoin`, `OnBehalf`, `OwnershipCheck` et `MemberErasure` sur un magasin en
mémoire à heure fixe — les rôles hérités le long de l'arbre, les cycles et la profondeur refusés, un sous-arbre archivé
et restauré d'un bloc, une seule unité par membre quand la configuration le demande, la borne des octrois, le dernier
propriétaire, l'archive en lecture seule, les raisons du refus d'une invitation, une utilisation perdue au profit d'un
autre membre, les événements émis après la validation et aucun après une annulation —, un contrat pour chaque magasin,
exécuté en mémoire et sur Doctrine DBAL avec SQLite en mémoire, les tables de chaque plateforme et leurs collations,
l'écouteur de schéma et la migration des tables, le *voter*, les quatre commandes, les pages d'administration avec une
doublure du moteur de gabarits, les recettes Flex, l'exportateur et l'effaceur de `phil/privacy-bundle`, les relations et
les tuples de `phil/access-bundle` — partitions comprises —, ainsi que la configuration et le câblage.

La suite `tests/Integration` exécute le même contrat sur MySQL 8.4 et PostgreSQL 17, avec la comparaison octet par octet
des colonnes de clé, la dernière utilisation d'une invitation prise par deux connexions, les chemins d'unités déplacées
en une seule instruction, et les tables des unités ajoutées à une base de données de la première version. Elle lit les
serveurs dans `PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL`, que `vendor/bin/phil-dev services up` démarre dans Docker, et elle
est ignorée sans eux.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/organizations-bundle/tests
vendor/bin/phil-dev services up
eval "$(vendor/bin/phil-dev services env)" && vendor/bin/phpunit packages/organizations-bundle/tests/Integration
```

Infection tient le bundle à un score de mutation couvert de 100 %.

La suite `tests/Functional` exécute les pages d'administration des unités dans une petite application, dessinées avec les
recettes du kit.

## Les bancs d'essai

`tests/Benchmark/PermissionCheckBench.php` mesure ce que coûte une vérification à une page : les permissions d'un
ensemble de rôles, `OrganizationAccess::can()` sur un magasin en mémoire, et le vote d'`OrganizationVoter` à partir d'un
jeton de sécurité — quelques microsecondes chacun sur un portable. `tests/Benchmark/UnitTreeBench.php` mesure, sur le
magasin DBAL au-dessus de SQLite et un arbre de 5 850 unités, les permissions héritées quatre niveaux plus bas, les
unités et les membres en dessous d'une unité, et le déplacement de 73 unités : d'environ 80 µs pour une vérification à
moins d'une milliseconde pour un déplacement.

## Tester une application

`store: memory` garde les organisations en mémoire, vidées entre les requêtes par `kernel.reset` :

```yaml
# config/packages/test/phil_organizations.yaml
phil_organizations:
    store: memory
    invitations:
        hash_key: 'a-test-key-of-thirty-two-bytes-at-least'
```

Avec le `MockClock` de `symfony/clock` comme service `clock`, les instants des invitations et leurs expirations sont
sous le contrôle du test. Un test écoute les événements de `Domain\Event` par le répartiteur d'événements pour voir ce
qui a changé.
