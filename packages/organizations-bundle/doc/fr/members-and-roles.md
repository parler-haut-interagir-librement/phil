<!-- phil-dev source-sha256=bc2008c54e49cc6b70090df555d5cf71fb8196e2ec34d949e68843bdde7980e1 -->
# Membres et rôles

## Les membres

Un membre est désigné par `MemberId`, l'identifiant opaque du sujet que donne le fournisseur d'utilisateurs de
l'application : de 1 à 128 lettres ASCII, chiffres, `_`, `.` ou `-`, commençant par une lettre ou un chiffre. Un
identifiant avec `@` est refusé, afin qu'une application dont l'identifiant d'utilisateur est l'adresse courriel s'en
aperçoive tout de suite plutôt que d'écrire des adresses dans des tables, des journaux et des événements. Le bundle ne
sait rien d'autre d'un membre — ni nom, ni adresse —, et fonctionne avec n'importe quel fournisseur d'utilisateurs.

```php
$member = MemberId::fromString($user->getId());
```

## Les organisations

`Organizations` les crée et les modifie :

```php
$acme = $organizations->create('Acme Guild', $member);                 // slug "acme-guild", $member owns it
$other = $organizations->create('Acme', $member, slug: 'acme-hq');      // a slug given is checked, never fixed
$organizations->rename($acme->id, $actor, 'Acme Builders', 'acme-builders');
$organizations->archive($acme->id, $actor);
$organizations->restore($acme->id, $actor);
$organizations->find('acme-builders');                                  // by identifier or slug
```

Un nom est un texte UTF-8, sans espaces aux extrémités, de 1 à 200 caractères, sans caractère de contrôle. Un *slug* est
produit à partir du nom par le *slugger* et numéroté — de `acme-2` à `acme-20` — quand il est pris ; un *slug* fourni est
utilisé tel quel, ou refusé avec `SlugTaken`. Son créateur est le premier propriétaire de l'organisation.

**Une organisation archivée est en lecture seule** : ses membres gardent `organization.view`, ceux qui peuvent
l'archiver gardent `organization.archive` pour la restaurer, et tout autre changement lève `OrganizationArchived`. Un
membre peut toujours la quitter.

## Rôles et permissions

Chaque adhésion porte de un à seize rôles. Trois sont intégrés :

| Rôle | Permissions |
|---|---|
| `owner` | Toutes les permissions, intégrées ou déclarées |
| `admin` | `organization.view`, `organization.manage`, `members.invite`, `members.manage`, `teams.manage`, `units.manage`, `organization.act_on_behalf` |
| `member` | `organization.view` |

Les permissions intégrées sont les constantes de `Permission` :

| Permission | Permet |
|---|---|
| `organization.view` | De voir l'organisation et ses membres |
| `organization.manage` | De la renommer, de changer son *slug* et son domaine vérifié |
| `organization.archive` | De l'archiver et de la restaurer |
| `members.invite` | De créer et de révoquer des invitations |
| `members.manage` | D'ajouter des membres, de changer leurs rôles, de les retirer |
| `owners.manage` | D'accorder et de retirer le rôle de propriétaire |
| `teams.manage` | De créer et de supprimer des équipes, de gérer les membres de n'importe quelle équipe |
| `units.manage` | De créer, renommer, déplacer, archiver et restaurer n'importe quelle unité, de gérer les membres de n'importe quelle unité ; toute permission d'unité dans chaque unité — voir [Unités](units.md) |
| `organization.act_on_behalf` | D'agir au nom de l'organisation |

La configuration déclare d'autres rôles, et ajoute des permissions à `admin` et à `member` :

```yaml
phil_organizations:
    roles:
        billing: { permissions: [billing.read, billing.pay] }
        member: { permissions: [wiki.read] }
```

Les permissions sont de la configuration, revue avec le code ; les rôles que détient chaque membre sont des données.
Une application vérifie une permission déclarée comme une permission intégrée, par `OrganizationAccess::can()` ou par le
*voter*.

## Changer les membres

`Members` ajoute, change et retire des membres :

```php
$members->add($acme->id, $actor, $member, ['admin']);
$members->changeRoles($acme->id, $actor, $member, ['member', 'billing']);
$members->remove($acme->id, $actor, $member);       // also from the organization's teams
$members->leave($acme->id, $member);
```

L'acteur détient `members.manage`, et **n'accorde, ne change ou ne retire que des rôles dont il détient lui-même les
permissions**, sur des membres dont les rôles sont dans les siens : un administrateur ne nomme, ne rétrograde ni ne
retire un propriétaire. Un rôle que la configuration ne déclare pas lève `UnknownRole` ; un changement au-delà des
permissions de l'acteur lève `PermissionDenied` ; un membre ajouté deux fois lève `AlreadyMember`.

## Le dernier propriétaire

**Le dernier propriétaire ne peut jamais partir, être retiré ni perdre le rôle de propriétaire** : le changement lève
`LastOwner`, dont le message nomme la règle. Chaque changement lit l'organisation verrouillée jusqu'à la fin de sa
transaction, de sorte que deux propriétaires qui se rétrogradent mutuellement au même moment s'exécutent l'un après
l'autre, et que l'un d'eux est refusé.

La seule exception est un droit de la personne : l'effacement des renseignements personnels du dernier propriétaire
retire son adhésion, et `OrganizationLeftWithoutOwner` est émis pour qu'un opérateur nomme un nouveau propriétaire. Voir
[Intégrations](integrations.md).

**Au moins deux propriétaires sont recommandés**, jamais exigés : le second nomme un nouveau propriétaire quand le
premier est parti. `OwnershipCheck` dit combien de propriétaires compte une organisation et liste celles qui sont sous la
recommandation ; `phil:organizations:show` avertit et `phil:organizations:check-owners` les liste, en échouant quand l'une
n'en a aucun.

## Vérifier l'accès

`OrganizationAccess` répond pour l'état courant :

```php
$access->can($acme->id, $member, 'billing.pay');
$access->hasRole($acme->id, $member, 'owner');
$access->permissions($acme->id, $member);           // sorted; read only in an archived organization
```

Dans un contrôleur ou un gabarit, le *voter* répond aux mêmes questions : voir
[Intégrations](integrations.md#le-voter).
