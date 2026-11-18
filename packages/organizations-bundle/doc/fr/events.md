<!-- phil-dev source-sha256=11068f41744dd3cbd698f3ae75d7ec88d4ab27a0b596b96aeb0f157072948be1 -->
# Événements

Chaque changement s'exécute dans une seule transaction du magasin, et ses événements sont émis par le répartiteur PSR-14
**une fois la transaction validée** : un écouteur n'entend jamais parler d'un changement annulé. Quand l'application
enveloppe un appel dans une transaction à elle sur la même connexion, les événements sont émis au retour de la partie du
bundle.

Les événements ne portent que des identifiants et des noms de rôle — jamais un nom, une adresse ou un jeton —, de sorte
qu'ils peuvent aller tels quels dans un journal, une piste d'audit ou un bus de messages. Un écouteur lit le reste dans
le magasin.

| Événement | Émis quand | Porte |
|---|---|---|
| `OrganizationCreated` | Une organisation est créée | organisation, premier propriétaire |
| `OrganizationRenamed` | Elle est renommée, son *slug* changé ou non | organisation, acteur |
| `OrganizationArchived`, `OrganizationRestored` | Elle est archivée, ou restaurée | organisation, acteur |
| `OrganizationDomainChanged` | Son domaine vérifié est consigné, changé ou retiré | organisation, acteur |
| `OrganizationLeftWithoutOwner` | L'effacement de son dernier propriétaire l'a laissée sans aucun | organisation |
| `MemberJoined` | Un membre adhère | organisation, membre, rôles, comment (`JoinMethod`), acteur quand quelqu'un l'a ajouté |
| `MemberRolesChanged` | Les rôles d'un membre changent | organisation, membre, rôles précédents, rôles, acteur |
| `MemberLeft` | Un membre part, est retiré ou effacé | organisation, membre, rôles détenus, pourquoi (`LeaveReason`), acteur quand quelqu'un l'a retiré |
| `TeamCreated`, `TeamDeleted` | Une équipe est créée, ou supprimée | organisation, équipe, acteur |
| `TeamMemberAdded` | Un membre rejoint une équipe | organisation, équipe, membre, rôles d'équipe, acteur |
| `TeamMemberRolesChanged` | Les rôles d'équipe d'un membre changent | organisation, équipe, membre, rôles précédents, rôles, acteur |
| `TeamMemberRemoved` | Un membre quitte une équipe, ou quitte l'organisation | organisation, équipe, membre, rôles d'équipe détenus, acteur quand quelqu'un l'a retiré |
| `InvitationCreated` | Une invitation est créée | organisation, invitation, acteur |
| `InvitationAccepted` | Une invitation est utilisée | organisation, invitation, membre |
| `InvitationRevoked` | Une invitation est révoquée, ou son créateur est effacé | organisation, invitation, acteur quand quelqu'un l'a révoquée |
| `ActedOnBehalf` | Un membre a agi au nom de l'organisation | organisation, véritable auteur, action, instant |

Ils se trouvent dans `Phil\OrganizationsBundle\Domain\Event`. Avec le répartiteur d'événements de Symfony, un écouteur
est un écouteur ordinaire :

```php
#[AsEventListener]
public function __invoke(MemberJoined $event): void
{
    $this->welcome->send($event->memberId, $event->organizationId);
}
```
