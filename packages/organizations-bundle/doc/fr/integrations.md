<!-- phil-dev source-sha256=9bf0bbb233aa2f579d73801baff695ced187b4b9610fb350b52615b0b26fded2 -->
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
```

| Changement | Tuples |
|---|---|
| Un membre adhère avec des rôles | `organization:<id>#<role>@member:<id>` pour chaque rôle |
| Les rôles d'un membre changent | les rôles retirés supprimés, les rôles accordés écrits |
| Un membre part | les tuples de ses rôles supprimés |
| Une équipe est créée | `team:<id>#organization@organization:<id>` |
| Un membre rejoint une équipe, change de rôles, la quitte | `team:<id>#<role>@member:<id>`, de la même façon |
| Une équipe est supprimée | chaque tuple de l'équipe |

Les tuples passent par le magasin de `phil/access-bundle`, dans la partition de sa portée : avec `partition: tenant`, ils
sont écrits dans le locataire actif, et un changement fait hors de tout locataire échoue plutôt que de les écrire nulle
part en particulier. Une application s'appuie sur eux dans son propre contributeur de modèle :

```php
$types['page'] = $types['page']->with([
    'organization' => Rewrite::direct('organization'),
    'editor' => Rewrite::fromRelated('organization', 'admin'),
]);
```

Les genres s'appellent `organization` et `team`, à moins que `access.organization_kind` et `access.team_kind` n'en
disent autrement ; un genre déjà présent dans le modèle arrête le conteneur. Les identifiants de membre suivent la règle
du `MemberId` de `phil/access-bundle`.

## `phil/audit-bundle`

`ActedOnBehalf` et chaque autre événement portent les identifiants qu'enregistre une piste d'audit : un écouteur les
ajoute à la piste de `phil/audit-bundle`, le véritable auteur à côté de l'organisation.
