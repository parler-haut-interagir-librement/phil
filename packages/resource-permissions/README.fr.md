<!-- phil-dev source-sha256=20d160e18a13737800bf7876f18b3002a79d4f475f330b5bc6f2b27fdc885e49 -->
# phil/resource-permissions

**Qui peut faire quoi sur chaque ressource : des relations entre des sujets et des objets, héritées par les arbres et
les groupes, vérifiées et listées dans les limites d'un budget.**

phil/resource-permissions répond aux questions d'accès à partir de faits écrits en tuples — *sujet, relation, objet* :
`member:ann` est un `viewer` de `doc:42`, les membres de `group:editors` sont des `editor` de `folder:7`, `doc:42` a
`folder:7` pour `parent`. Un modèle déclare, pour chaque type d'objet, ses relations et la façon dont chacune est
calculée : à partir des tuples écrits sur lui, à partir d'une autre relation du même objet (tout éditeur est un
lecteur), à partir d'une relation des objets vers lesquels il pointe (les lecteurs du dossier parent voient le
document), ou par une union, une intersection ou une exclusion de ces calculs. `check()` dit si un sujet détient une
relation, `explain()` donne la chaîne de tuples qui la lui accorde, et `objects()` liste les objets d'un type sur
lesquels un sujet la détient, pour le `IN (…)` d'une requête.

Sur les mêmes tuples, `TreePermissions` donne les permissions par ressource d'un wiki, d'une bibliothèque ou d'un
ensemble de secrets de campagne : chaque nœud d'un arbre peut porter des entrées qui permettent ou refusent une action
à un rôle ou à tous les autres, héritées le long de l'arbre, où l'entrée la plus précise l'emporte — le nœud le plus
proche, puis un rôle avant tous les autres, puis un refus avant une permission.

Il convient à toute application PHP dont l'accès suit le contenu : documents et dossiers partagés, groupes dans des
groupes, éléments visibles aux membres seulement, accès qui expire à une date. Chaque parcours est borné par un
`Budget` — profondeur, lectures du magasin, ensembles de sujets derrière une relation, candidats d'une liste —, un
cycle est coupé de lui-même, et un parcours qui dépasse son budget lève une exception plutôt que de répondre : un
graphe profond ou hostile ne devient jamais une page lente ni un oui à tort.

C'est une bibliothèque PHP simple, qui repose sur `phil/resource` et `psr/clock`. Les tuples vivent dans un magasin :
en mémoire pour les tests et les exécutions uniques, ou dans une table d'une connexion Doctrine DBAL sur PostgreSQL,
MySQL, MariaDB ou SQLite, indexée dans les deux sens pour qu'une vérification et une longue liste ne coûtent chacune
que quelques lectures indexées. Un tuple peut porter une expiration : à partir de cette seconde, aucun magasin ne le
lit.

## État

| Partie | État |
|---|---|
| Sujets (un objet, tous les objets d'un type, un ensemble de sujets), tuples avec expiration facultative | Implémenté et testé |
| Le modèle : types, relations, réécritures directes, calculées et héritées, union, intersection et exclusion, vérifié à la construction | Implémenté et testé |
| `check()`, `explain()` et `objects()`, bornés par un budget, cycles coupés, exclusions refermées sur une coupure | Implémenté et testé |
| `RelationWriter` : tuples vérifiés par rapport au modèle, écrits ou supprimés d'un seul coup | Implémenté et testé |
| `TreePermissions` : entrées par nœud et par action pour un rôle ou pour tous les autres, la plus précise l'emporte | Implémenté et testé |
| Magasin en mémoire ; magasin Doctrine DBAL avec collation octet par octet, schéma et écouteur de l'ORM, purge, testé sur MySQL 8.4 et PostgreSQL 17 | Implémenté et testé |
| Une cible de fuzzing du vérificateur ; un banc d'essai d'une vérification et d'une liste | Implémenté |
| Voters Symfony, visibilité, niveaux de partage, groupes d'accès et « voir comme » sur ce modèle | Implémenté dans `phil/access-bundle` |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, la déclaration des services dans Symfony. |
| [Le modèle](doc/fr/model.md) | Sujets, tuples, types, relations et réécritures, et comment le modèle est vérifié. |
| [Vérifier et lister](doc/fr/checking.md) | `check()`, `explain()`, `objects()`, le budget, les cycles et l'expiration. |
| [Permissions dans un arbre](doc/fr/tree.md) | `TreePermissions` : entrées, héritage, entrée la plus précise, déplacement de nœuds. |
| [Stockage](doc/fr/storage.md) | Les magasins, la table et ses index, la collation, l'écouteur de schéma, la purge. |
| [Exceptions](doc/fr/exceptions.md) | `ResourcePermissionsException` et ses quatre exceptions. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports et les arguments qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, sa cible de fuzzing et son banc d'essai, et comment tester du code qui vérifie l'accès. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/resource-permissions
```

```php
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\InMemoryTupleStore;
use Phil\ResourcePermissions\Model\Model;
use Phil\ResourcePermissions\Model\Rewrite;
use Phil\ResourcePermissions\Model\TypeDefinition;
use Phil\ResourcePermissions\RelationChecker;
use Phil\ResourcePermissions\RelationWriter;
use Phil\ResourcePermissions\Subject;
use Symfony\Component\Clock\NativeClock;

$model = new Model(
    TypeDefinition::of('member'),
    TypeDefinition::of('group', ['member' => Rewrite::direct('member', 'group#member')]),
    TypeDefinition::of('folder', [
        'parent' => Rewrite::direct('folder'),
        'editor' => Rewrite::direct('member', 'group#member'),
        'viewer' => Rewrite::union(
            Rewrite::direct('member', 'member:*', 'group#member'),
            Rewrite::computed('editor'),
            Rewrite::fromRelated('parent', 'viewer'),
        ),
    ]),
);

$store = new InMemoryTupleStore(new NativeClock());
$writer = new RelationWriter($model, $store);
$writer->grant(ResourceReference::of('folder', 'root'), 'editor', Subject::set(ResourceReference::of('group', 'staff'), 'member'));
$writer->grant(ResourceReference::of('group', 'staff'), 'member', ResourceReference::of('member', 'ann'));
$writer->grant(ResourceReference::of('folder', 'guides'), 'parent', ResourceReference::of('folder', 'root'));

$checker = new RelationChecker($model, $store);
$checker->check(ResourceReference::of('member', 'ann'), 'viewer', ResourceReference::of('folder', 'guides')); // true
$checker->objects(ResourceReference::of('member', 'ann'), 'viewer', 'folder')->ids();                         // ['guides', 'root']
```

## Prérequis

PHP 8.5 ou une version plus récente ; `doctrine/dbal` 4.5 ou une version plus récente pour le magasin en base de
données, `doctrine/orm` 3.7 ou une version plus récente pour son écouteur de schéma.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
