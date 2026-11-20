<!-- phil-dev source-sha256=8ea2e07f8f765276de7dddebdd14d96411406394eef33b2350cb0e8addf41571 -->
# Unités

Une organisation détient des **unités** — départements, services, succursales, classes — en arbre : une unité est une
racine, ou l'enfant d'une autre unité de la même organisation. Une unité a un nom et une place dans l'arbre ; elle est
active ou archivée ; des membres de l'organisation lui appartiennent avec des **rôles d'unité**, qui valent dans l'unité
et dans chaque unité en dessous, comme l'explique [Héritage des rôles](role-inheritance.md).

## L'arbre

`Application\Units` change l'arbre ; chaque changement s'exécute dans une seule transaction, verrouille son organisation
et émet son événement une fois validé :

```php
$sales = $units->create($acme->id, $actor, 'Sales');                    // a root
$east = $units->create($acme->id, $actor, 'East', $sales->id);           // under Sales
$units->rename($east->id, $actor, 'East coast');
$units->move($east->id, $actor, $support->id);                          // with every unit below it
$units->move($east->id, $actor, null);                                  // to the root
$units->archive($sales->id, $actor);                                    // with every unit below it
$units->restore($sales->id, $actor);
```

- **Créer.** Le parent est une unité active de la même organisation ; sans parent, l'unité est une racine. Son
  identifiant est un UUID version 7.
- **Renommer.** Le nom d'une unité compte de 1 à 200 caractères, comme celui d'une organisation ; la renommer du même
  nom ne change rien.
- **Déplacer.** L'unité se déplace avec chaque unité en dessous. Un déplacement sous l'unité elle-même ou sous l'une des
  unités qui sont en dessous d'elle lève `UnitCycle` ; un déplacement vers le parent qu'elle a déjà ne change rien. Deux
  déplacements d'une même organisation ne s'exécutent jamais en même temps, de sorte qu'ils ne peuvent pas faire un
  cycle ensemble.
- **Archiver.** L'unité et chaque unité en dessous qui est encore active sont archivées au même instant ; leurs membres
  restent. Une unité archivée est en lecture seule : aucune unité n'est créée sous elle ni déplacée dedans, elle n'est ni
  renommée ni déplacée, et ses membres ne changent pas, sauf un membre qui la quitte. `UnitArchived` le dit.
- **Restaurer.** L'unité et les unités en dessous archivées au même instant qu'elle reviennent ; une unité archivée
  avant reste archivée. Une unité dont le parent est archivé n'est restaurée qu'une fois son parent restauré.

### Les limites

Une unité est profonde de **16 niveaux** au plus, une racine étant au niveau 1, et une organisation détient au plus
**10 000 unités**, archivées comprises : un changement au-delà de l'une ou l'autre lève `InvalidOrganization`. Un
déplacement vérifie l'unité la plus profonde en dessous de celle qui se déplace. Ces bornes gardent chaque question de
l'arbre à une seule requête bornée.

## Les membres

```php
$units->addMember($east->id, $actor, $member, ['instructor']);
$units->changeRoles($east->id, $actor, $member, ['manager']);
$units->removeMember($east->id, $actor, $member);                      // $actor === $member: the member leaves
```

Un membre d'une unité est un membre de l'organisation : y ajouter quelqu'un d'autre lève `NotAMember`, et ajouter un
membre deux fois lève `AlreadyMember`. Un membre qui quitte l'organisation, en est retiré ou est effacé quitte ses
unités, avec un `UnitMemberRemoved` pour chacune.

Avec `phil_organizations.units.single_membership: true`, un membre appartient à **une seule unité d'une organisation au
plus** : l'ajouter à une deuxième lève `AlreadyMember::ofAnotherUnit()`. Il est retiré de la première unité avant de
rejoindre une autre. La règle vaut par organisation : un membre de deux organisations appartient à une unité dans
chacune.

## Qui peut changer quoi

| Changement | Permis à |
|---|---|
| Créer une unité racine | Les membres dont les rôles d'organisation accordent `units.manage` — les propriétaires et les administrateurs par défaut |
| Créer une unité sous une unité, renommer une unité | Ceux qui gèrent cette unité |
| Déplacer, archiver ou restaurer une unité | Ceux qui gèrent son parent — `units.manage` pour une racine — et, pour un déplacement, le nouveau parent aussi |
| Ajouter, changer ou retirer un membre | Ceux qui gèrent l'unité, dans les limites des rôles d'unité qu'ils y détiennent |

Un membre **gère** une unité quand ses rôles d'organisation accordent `units.manage`, ou quand les rôles d'unité qu'il
détient dans l'unité ou au-dessus d'elle accordent `unit.manage` — le `manager` intégré. Un gestionnaire dirige donc les
unités en dessous de son unité, pas l'unité elle-même : celui qui l'y a placé la déplace et l'archive. Il n'accorde, ne
change et ne retire que des rôles d'unité dont il détient les permissions dans cette unité ; `units.manage` accorde
chaque rôle d'unité. Un refus lève `PermissionDenied` ; un changement dans une organisation archivée lève
`OrganizationArchived`.

## Les questions des autres modules

`Application\UnitTree` répond à ce que les autres modules demandent à l'arbre — un catalogue pour une unité, un rapport
par unité, un tableau de bord — en lecture seule et sans vérification de permission. C'est l'API stable sur laquelle un
module s'appuie :

| Méthode | Répond |
|---|---|
| `unit()`, `get()` | Une unité, ou `null` / `UnknownUnit` |
| `units($organizationId, $withArchived)` | Chaque unité de l'organisation, triée par chemin |
| `roots()`, `children()` | Les racines, ou les enfants d'une unité |
| `ancestors($unitId)` | Les unités au-dessus, depuis la racine |
| `descendants($unitId, $withSelf, $withArchived)` | Les unités en dessous |
| `isWithin($unitId, $ancestorId)` | Si une unité est une autre ou est en dessous d'elle |
| `members($unitId, $limit, $afterMember)` | Les membres de l'unité elle-même, triés, une page à la fois |
| `membersWithin($unitId, $limit, $afterMember)` | Les membres de l'unité et des unités en dessous, chacun une fois |
| `membershipsOf($organizationId, $memberId)` | Les adhésions d'un membre aux unités |
| `unitIdsOf($organizationId, $memberId, $withAncestors)` | Les unités auxquelles un membre appartient et, avec les ancêtres, chaque unité au-dessus d'elles |

Les unités archivées sont laissées de côté sauf si on les demande. Chaque liste d'unités est triée par chemin : un parent
vient avant ses enfants, et l'ordre des frères et sœurs suit leurs identifiants, octet par octet. Un écran qui liste des
unités sœurs par nom les trie lui-même.

Un catalogue qui appartient à une unité et qui est offert aux membres de cette unité et des unités en dessous lit
`unitIdsOf($organizationId, $member, withAncestors: true)` et garde les catalogues de ces unités.

## Comment l'arbre est stocké

Chaque ligne d'unité conserve son parent et son **chemin matérialisé**, les identifiants de ses ancêtres et le sien,
écrits `/root/child/unit/`, avec sa profondeur. Le chemin est indexé avec l'organisation et se compare octet par octet —
`ascii_bin` sur MySQL et MariaDB, la collation `C` sur PostgreSQL, l'ordre binaire de SQLite. Les unités en dessous d'une
unité sont les chemins qui se trient de son chemin au même chemin dont la dernière `/` est devenue `0`, une seule plage
de l'index, sans motif `LIKE` à échapper. Les ancêtres d'une unité sont nommés par son chemin, de sorte que les rôles
d'unité qu'un membre détient au-dessus d'une unité sont une lecture indexée de `phil_organization_unit_member`. Un
déplacement réécrit chaque chemin en dessous par un seul `UPDATE`.

Une table de fermeture a été mesurée contre lui : une deuxième table qui contient une ligne par ancêtre de chaque unité,
jointe pour chaque question. Sur un arbre de 5 850 unités — 10 racines de 8 enfants chacune, quatre niveaux de
profondeur — avec 23 400 adhésions aux unités, la moyenne de chaque opération sur quelques centaines d'exécutions était :

| Opération | Chemin, MySQL 8.4 | Fermeture, MySQL 8.4 | Chemin, PostgreSQL 17 | Fermeture, PostgreSQL 17 |
|---|---|---|---|---|
| Les rôles d'un membre au-dessus d'une unité de quatrième niveau | 351 µs (deux requêtes) | 199 µs | 443 µs (deux requêtes) | 483 µs |
| Les 73 unités en dessous d'une unité du deuxième niveau | 228 µs | 286 µs | 284 µs | 796 µs |
| Les membres dans une unité du deuxième niveau | 478 µs | 466 µs | 2 491 µs | 2 541 µs |
| Un déplacement de 73 unités | 1 435 µs | 2 902 µs | 1 469 µs | 2 959 µs |
| Une nouvelle unité | 270 µs | 942 µs | 282 µs | 1 116 µs |

Le chemin coûte une lecture de l'unité avant les rôles au-dessus d'elle, qu'une vérification de permission fait de toute
façon pour savoir si l'unité est archivée ; la table de fermeture paie une table quatre fois plus grosse — 23 575 lignes
pour 5 850 unités — et de deux à quatre fois le coût de chaque écriture. Le bundle garde le chemin.
`tests/Benchmark/UnitTreeBench.php` mesure les mêmes opérations par les services du bundle, sur SQLite.

Les tables sont `phil_organization_unit` et `phil_organization_unit_member` ; [Installation](installation.md#les-tables)
dit comment elles sont créées.

## Les événements

| Événement | Émis quand | Porte |
|---|---|---|
| `UnitCreated` | Une unité est créée | organisation, unité, parent ou `null`, acteur |
| `UnitRenamed` | Une unité est renommée | organisation, unité, acteur |
| `UnitMoved` | Une unité se déplace | organisation, unité, parent précédent, parent, acteur |
| `UnitArchived`, `UnitRestored` | Une unité est archivée, ou restaurée | organisation, unité, chaque unité archivée ou restaurée avec elle, acteur |
| `UnitMemberAdded` | Un membre rejoint une unité | organisation, unité, membre, rôles d'unité, acteur |
| `UnitMemberRolesChanged` | Les rôles d'unité d'un membre changent | organisation, unité, membre, rôles précédents, rôles, acteur |
| `UnitMemberRemoved` | Un membre quitte une unité, ou l'organisation | organisation, unité, membre, rôles d'unité détenus, acteur quand quelqu'un l'a retiré |

Les unités en dessous d'une unité qui se déplace ne sont pas annoncées une à une : `UnitMoved` nomme l'unité, et leurs
chemins suivent.

## Pages d'administration

Le bundle dessine les pages des unités avec les recettes du kit PHIL UX — `Badge`, `Button`, `Input`, `NativeSelect`,
`Table` et `Tree` —, que le Symfony UX Toolkit installe dans l'application :

```sh
php bin/console ux:install badge button input native-select table tree --kit=github.com/parler-haut-interagir-librement/ux-kit
```

La recette du bundle importe leurs routes sous `/admin/organizations` :

| Route | Page |
|---|---|
| `phil_organizations_units` | L'arbre d'une organisation, le formulaire d'une nouvelle unité |
| `phil_organizations_units_create` | La nouvelle unité, envoyée |
| `phil_organizations_unit` | Une unité : les unités au-dessus, son état, les unités en dessous, ses membres et ses formulaires |
| `phil_organizations_unit_rename`, `_move`, `_archive`, `_restore` | Les formulaires d'une unité |
| `phil_organizations_unit_member_add`, `_member_remove` | Les formulaires de ses membres |

Elles exigent Twig, le routeur, la protection CSRF de FrameworkBundle et le stockage des jetons de la sécurité de
Symfony. Chaque membre connecté de l'organisation voit les pages ; chaque formulaire s'affiche à ceux qui peuvent s'en
servir, et chaque changement passe par `Units`, qui vérifie encore les permissions. Un refus portant sur le contenu d'un
changement affiche de nouveau la page avec sa raison et un `422` ; un changement que le membre ne peut pas faire est un
`403` ; une unité ou une organisation inconnue, un `404`. Les formulaires portent le jeton CSRF sans état de Symfony
`phil_organizations_units`, que le bundle déclare, et un formulaire envoyé d'un autre site est refusé par un `400`.

Les pages prolongent `phil_organizations.units.layout`, qui remplit les blocs `title` et `main`. Leurs libellés sont dans
le domaine de traduction `phil_organizations`, en anglais et en français. Les recettes sont nommées d'après le
sous-répertoire des composants de la configuration `ux_toolkit`, ou `phil_organizations.units.kit_subdirectory` là où le
Toolkit n'est pas activé, comme en production.
