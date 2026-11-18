<!-- phil-dev source-sha256=d638c7db9a64c58ccc0868ca45408aa610ea283d6337950841bcf35e99f56c09 -->
# Tests

## Les tests du bundle

La suite `tests/Unit` teste les identifiants, les noms, les rôles et les domaines ainsi que leurs règles, le modèle et
les états d'une invitation, les catalogues de rôles, `Organizations`, `Members`, `Teams`, `Invitations`, `DomainJoin`,
`OnBehalf`, `OwnershipCheck` et `MemberErasure` sur un magasin en mémoire à heure fixe — la borne des octrois, le dernier
propriétaire, l'archive en lecture seule, les raisons du refus d'une invitation, une utilisation perdue au profit d'un
autre membre, les événements émis après la validation et aucun après une annulation —, un contrat pour chaque magasin,
exécuté en mémoire et sur Doctrine DBAL avec SQLite en mémoire, les tables de chaque plateforme et leurs collations,
l'écouteur de schéma, le *voter*, les quatre commandes, l'exportateur et l'effaceur de `phil/privacy-bundle`, les
relations et les tuples de `phil/access-bundle` — partitions comprises —, ainsi que la configuration et le câblage.

La suite `tests/Integration` exécute le même contrat sur MySQL 8.4 et PostgreSQL 17, avec la comparaison octet par octet
des colonnes de clé et la dernière utilisation d'une invitation prise par deux connexions. Elle lit les serveurs dans
`PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL`, que `vendor/bin/phil-dev services up` démarre dans Docker, et elle
est ignorée sans eux.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/organizations-bundle/tests
vendor/bin/phil-dev services up
eval "$(vendor/bin/phil-dev services env)" && vendor/bin/phpunit packages/organizations-bundle/tests/Integration
```

Infection tient le bundle à un score de mutation couvert de 100 %.

## Le banc d'essai

`tests/Benchmark/PermissionCheckBench.php` mesure ce que coûte une vérification à une page : les permissions d'un
ensemble de rôles, `OrganizationAccess::can()` sur un magasin en mémoire, et le vote d'`OrganizationVoter` à partir d'un
jeton de sécurité — quelques microsecondes chacun sur un portable.

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
