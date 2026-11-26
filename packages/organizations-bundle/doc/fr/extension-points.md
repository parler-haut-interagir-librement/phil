<!-- phil-dev source-sha256=7102edf11c6e878604046c011abc555258af39545829899a372bff37e07c3255 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les modifier, et son CHANGELOG dit comment.

## Un magasin

Tout service qui implémente `Phil\OrganizationsBundle\Application\Contract\OrganizationStore`, nommé dans `store`,
remplace celui du bundle : des organisations sur une autre API de base de données, derrière un cache. Pour conserver des
unités, il implémente aussi `Application\Contract\UnitStore`, sur les mêmes transactions ; sans lui, les unités, leurs
pages et `UnitTree` sont laissés de côté, et le reste fonctionne comme avant. Il tient les promesses que vérifient les
tests de contrat du bundle, `tests/Unit/Infrastructure/StoreTestCase.php` :

- `transactional()` valide le travail à son retour et annule tout quand il lève une exception ;
- `organization($id, lock: true)` retient l'organisation jusqu'à la fin de la transaction, de sorte que deux changements
  d'une même organisation s'exécutent l'un après l'autre ;
- `useInvitation()` compte une utilisation en une seule étape atomique, seulement tant que l'invitation n'est ni
  révoquée, ni épuisée, ni expirée, et dit si elle l'a fait ;
- `deleteMembership()` supprime aussi les adhésions du membre aux équipes et aux unités de cette organisation,
  `deleteTeam()` les adhésions de l'équipe ;
- le déplacement d'une unité réécrit le chemin de chaque unité en dessous, et les unités en dessous d'une unité sont
  celles dont le chemin commence par le sien ;
- les listes sont triées — organisations par *slug*, membres par identifiant, unités par chemin, invitations de la plus
  récente à la plus ancienne — et les identifiants, les *slugs*, les chemins et les empreintes se comparent octet par
  octet.

```yaml
phil_organizations:
    store: App\Organizations\CachedStore
```

## Qui est un utilisateur connecté

Un `Phil\OrganizationsBundle\Application\Contract\MemberOfUser`, nommé dans `member_of_user`, donne au *voter* le
membre que représente un utilisateur Symfony : voir [Intégrations](integrations.md#le-voter).

## Les *slugs*

Tout `Phil\Slug\Slugger`, nommé dans `slugger`, produit les *slugs* des organisations et des équipes à partir de leurs
noms : un qui retire les articles initiaux, un propre à une locale. `phil/slug-bundle` déclare des *sluggers* nommés.

## Rôles et permissions

Les rôles et leurs permissions sont de la configuration — `roles`, `team_roles` et `unit_roles` —, jamais du code : voir
[Membres et rôles](members-and-roles.md#rôles-et-permissions) et [Héritage des rôles](role-inheritance.md).
`RoleCatalog` (l'alias de `phil_organizations.roles`), `phil_organizations.team_roles` et
`phil_organizations.unit_roles` sont des services, pour une application qui les liste.

## L'arbre des unités

`UnitTree` est l'API en lecture seule sur laquelle un autre module s'appuie — un catalogue par unité, un rapport par
unité —, et `UnitAccess` la vérification de permission qu'un module fait dans une unité. Leurs méthodes sont listées dans
[Unités](units.md#les-questions-des-autres-modules).

## Les pages des unités

Les pages prolongent `units.layout`, dessinent les recettes du kit sous `units.kit_subdirectory`, et prennent leurs
libellés dans le domaine de traduction `phil_organizations`, qu'une application remplace dans ses propres
`translations/phil_organizations.<locale>.*`. Une application qui dessine ses propres écrans laisse de côté les routes et
appelle `Units` et `UnitTree`.

## Les événements

Chaque changement émet un événement de `Domain\Event` une fois validé : un écouteur envoie un accueil, écrit une piste
d'audit, rafraîchit un cache. Voir [Événements](events.md).

## Le modèle d'accès

Avec `phil/access-bundle`, les genres `organization`, `team` et `unit` et leurs relations font partie du modèle d'accès,
et le `ModelContributor` propre à une application s'appuie sur eux. Voir
[Intégrations](integrations.md#philaccess-bundle).

## Les services

`Organizations`, `Members`, `Teams`, `Units`, `UnitTree`, `UnitAccess`, `Invitations`, `DomainJoin`, `OnBehalf`,
`OwnershipCheck`, `MemberErasure`, `OrganizationAccess`, `RoleCatalog`, `OrganizationStore` et `UnitStore` sont des
services publics, câblables automatiquement par leur classe.
