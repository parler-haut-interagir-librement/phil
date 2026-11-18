<!-- phil-dev source-sha256=ff9d21e858c89994edb07b8c3531eafa13471d94e37dd728ae3f8b97d60afcd2 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les modifier, et son CHANGELOG dit comment.

## Un magasin

Tout service qui implémente `Phil\OrganizationsBundle\Application\Contract\OrganizationStore`, nommé dans `store`,
remplace celui du bundle : des organisations sur une autre API de base de données, derrière un cache. Il tient les
promesses que vérifient les tests de contrat du bundle, `tests/Unit/Infrastructure/StoreTestCase.php` :

- `transactional()` valide le travail à son retour et annule tout quand il lève une exception ;
- `organization($id, lock: true)` retient l'organisation jusqu'à la fin de la transaction, de sorte que deux changements
  d'une même organisation s'exécutent l'un après l'autre ;
- `useInvitation()` compte une utilisation en une seule étape atomique, seulement tant que l'invitation n'est ni
  révoquée, ni épuisée, ni expirée, et dit si elle l'a fait ;
- `deleteMembership()` supprime aussi les adhésions du membre aux équipes de cette organisation, `deleteTeam()` les
  adhésions de l'équipe ;
- les listes sont triées — organisations par *slug*, membres par identifiant, invitations de la plus récente à la plus
  ancienne — et les identifiants, les *slugs* et les empreintes se comparent octet par octet.

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

Les rôles et leurs permissions sont de la configuration — `roles` et `team_roles` —, jamais du code : voir
[Membres et rôles](members-and-roles.md#rôles-et-permissions). `RoleCatalog` (l'alias de `phil_organizations.roles`) et
`phil_organizations.team_roles` sont des services, pour une application qui les liste.

## Les événements

Chaque changement émet un événement de `Domain\Event` une fois validé : un écouteur envoie un accueil, écrit une piste
d'audit, rafraîchit un cache. Voir [Événements](events.md).

## Le modèle d'accès

Avec `phil/access-bundle`, les genres `organization` et `team` et leurs relations font partie du modèle d'accès, et le
`ModelContributor` propre à une application s'appuie sur eux. Voir [Intégrations](integrations.md#philaccess-bundle).

## Les services

`Organizations`, `Members`, `Teams`, `Invitations`, `DomainJoin`, `OnBehalf`, `OwnershipCheck`, `MemberErasure`,
`OrganizationAccess`, `RoleCatalog` et `OrganizationStore` sont des services publics, câblables automatiquement par leur
classe.
