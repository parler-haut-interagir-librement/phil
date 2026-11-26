<!-- phil-dev source-sha256=6a518c9e1b58063d1e7450ee95654b6dfeddbd5c4d77e4d83dd919771b892204 -->
# Intégrations

## Le voter

Avec `symfony/security-core`, `OrganizationVoter` vote sur les permissions et les rôles qu'un membre détient dans une
organisation, donnée comme `Organization`, comme `OrganizationId` ou comme texte de son identifiant :

```php
$this->denyAccessUnlessGranted('organization:members.invite', $organization);
$this->denyAccessUnlessGranted('organization_role:owner', $organizationId);
```

```twig
{% if is_granted('organization:billing.pay', organization) %}…{% endif %}
```

Une permission suit les rôles du catalogue et la règle de lecture seule d'une organisation archivée. Quiconque n'est pas
connecté, n'est pas membre, ou dont l'utilisateur n'est pas un membre, se voit refuser l'accès, et le vote en donne la
raison.

Le même *voter* répond à `unit:<permission>` et à `unit_role:<role>` sur une `Unit`, son `UnitId` ou le texte de son
identifiant, les rôles d'unité détenus au-dessus de l'unité compris, comme l'explique
[Héritage des rôles](role-inheritance.md#le-voter).

Le *voter* demande à `MemberOfUser` quel membre est un utilisateur connecté. Par défaut, l'identifiant de l'utilisateur
est le membre quand c'est un identifiant stable ; une application dont les utilisateurs se connectent par adresse
courriel nomme le sien :

```php
final readonly class AccountMember implements MemberOfUser
{
    public function memberId(UserInterface $user): ?MemberId
    {
        return $user instanceof Account ? MemberId::fromString($user->subject) : null;
    }
}
```

```yaml
phil_organizations:
    member_of_user: App\Security\AccountMember
```

## `phil/privacy-bundle`

Quand `phil/privacy-bundle` est installé, `OrganizationsPrivacy` en est l'exportateur et l'effaceur pour le module
`organizations`, la personne concernée étant l'identifiant de membre :

- l'exportation liste les adhésions du membre — organisation, nom, rôles, comment et quand il a adhéré —, ses adhésions
  à des équipes et les invitations qu'il a créées ;
- l'effacement retire ses adhésions et ses adhésions à des équipes, révoque les invitations qu'il a créées et qui
  pouvaient encore servir, et l'oublie comme créateur des autres ; le rapport dit `deleted` pour les adhésions, les rôles
  et les adhésions à des équipes, `pseudonymised` pour les invitations. Effacer deux fois ne change rien de plus.

Un droit de la personne n'est jamais refusé au nom de l'organisation : le dernier propriétaire est effacé lui aussi, et
`OrganizationLeftWithoutOwner` est émis pour qu'un opérateur nomme un nouveau propriétaire ;
`phil:organizations:check-owners` échoue tant qu'aucun n'est nommé. Le même effacement est `MemberErasure::erase()`.

## `phil/access-bundle`

Quand `phil/access-bundle` est enregistré — ou avec `access.enabled: true` —, le bundle ajoute les organisations à son
modèle d'accès et publie les adhésions et les rôles comme tuples de `phil/resource-permissions`, une fois chaque
changement validé :

```text
organization: owner, admin, member, billing, … = [member]
team:         organization = [organization]; maintainer, member, reviewer, … = [member]
unit:         organization = [organization]; parent = [unit]; manager, member, instructor, … = [member] or <role> from parent
```

| Changement | Tuples |
|---|---|
| Un membre adhère avec des rôles | `organization:<id>#<role>@member:<id>` pour chaque rôle |
| Les rôles d'un membre changent | les rôles retirés supprimés, les rôles accordés écrits |
| Un membre part | les tuples de ses rôles supprimés |
| Une équipe est créée | `team:<id>#organization@organization:<id>` |
| Un membre rejoint une équipe, change de rôles, la quitte | `team:<id>#<role>@member:<id>`, de la même façon |
| Une équipe est supprimée | chaque tuple de l'équipe |
| Une unité est créée | `unit:<id>#organization@organization:<id>`, et `unit:<id>#parent@unit:<id>` sous un parent |
| Une unité se déplace | son tuple `parent` réécrit : l'ancien supprimé, le nouveau écrit, aucun pour une racine |
| Un membre rejoint une unité, change de rôles d'unité, la quitte | `unit:<id>#<role>@member:<id>`, de la même façon |

Les tuples passent par le magasin de `phil/access-bundle`, dans la partition de sa portée : avec `partition: tenant`, ils
sont écrits dans le locataire actif, et un changement fait hors de tout locataire échoue plutôt que de les écrire nulle
part en particulier. Une application s'appuie sur eux dans son propre contributeur de modèle :

```php
$types['page'] = $types['page']->with([
    'organization' => Rewrite::direct('organization'),
    'editor' => Rewrite::fromRelated('organization', 'admin'),
]);
```

### Les unités dans le modèle d'accès

Un rôle d'unité vaut dans son unité et dans chaque unité en dessous, comme l'explique [Héritage des
rôles](role-inheritance.md), et le modèle d'accès dit la même chose : chaque rôle d'unité est une relation donnée aux
membres directement **ou** détenue par `parent`, de sorte qu'un gestionnaire de Sales est `manager` de East et de chaque
unité en dessous, et qu'un déplacement de East sous Support la sort de leur portée dans la même écriture. Les relations
d'une unité suivent les rôles de `unit_roles` ; un rôle d'unité nommé `organization` ou `parent` arrête le conteneur.
Une relation propre à l'application s'appuie sur elles :

```php
$types['course'] = $types['course']->with([
    'unit' => Rewrite::direct('unit'),
    'assign' => Rewrite::fromRelated('unit', 'instructor'),    // instructors of the unit and of the units above it
]);
```

Un arbre compte seize niveaux de profondeur au plus, dans les 32 relations suivies l'une dans l'autre du `Budget` par
défaut de `phil/resource-permissions`. Les rôles d'organisation qui accordent `units.manage` ne sont pas réécrits dans
les relations d'unité : une relation de l'application qui doit les atteindre nomme `fromRelated('organization',
'admin')` à côté de celle de l'unité. Une unité archivée garde ses tuples, comme le fait une organisation archivée ;
`UnitAccess` et le *voter* la refusent, et une vérification par le modèle d'accès lit elle-même si l'unité est archivée.

Les unités restent hors du modèle d'accès avec `access.unit_kind: null`, ou quand le magasin ne garde aucune unité.

### Les noms des genres

Les genres s'appellent `organization`, `team` et `unit`, à moins que `access.organization_kind`, `access.team_kind` et
`access.unit_kind` n'en disent autrement ; un genre déjà présent dans le modèle arrête le conteneur. Les identifiants de
membre suivent la règle du `MemberId` de `phil/access-bundle`.

## `phil/audit-bundle`

`ActedOnBehalf` et chaque autre événement portent les identifiants qu'enregistre une piste d'audit : un écouteur les
ajoute à la piste de `phil/audit-bundle`, le véritable auteur à côté de l'organisation.
