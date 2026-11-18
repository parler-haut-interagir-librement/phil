<!-- phil-dev source-sha256=3dab01848534797f31d6e4c2dc197929fe67a1e5cb8be435bdf71ff5e31a0c60 -->
# Équipes

Une équipe appartient à une organisation : un nom, un *slug* unique dans l'organisation, et ses membres, chacun membre de
l'organisation avec un ou plusieurs **rôles d'équipe**. Deux sont intégrés, et la configuration en déclare d'autres :

| Rôle d'équipe | Permissions |
|---|---|
| `maintainer` | `team.manage` |
| `member` | aucune |

```yaml
phil_organizations:
    team_roles:
        reviewer: { permissions: [review.approve] }
```

## Gérer les équipes

```php
$design = $teams->create($acme->id, $actor, 'Design');
$teams->addMember($design->id, $actor, $member, ['maintainer']);
$teams->changeRoles($design->id, $actor, $member, ['member', 'reviewer']);
$teams->removeMember($design->id, $actor, $member);     // the member themselves leaves
$teams->rename($design->id, $actor, 'Product design', 'product-design');
$teams->delete($design->id, $actor);
```

Un membre qui détient `teams.manage` dans l'organisation crée, renomme et supprime des équipes et gère les membres de
n'importe quelle équipe, avec tous les rôles d'équipe. Un membre dont les rôles d'équipe accordent `team.manage` — un
mainteneur — renomme cette équipe et gère ses membres, en n'accordant, ne changeant ou ne retirant que des rôles
d'équipe dont il détient les permissions dans l'équipe. Seuls les membres de l'organisation rejoignent ses équipes ; un
membre qui quitte l'organisation, ou qui en est retiré ou effacé, quitte aussi ses équipes.

`Teams::permissions()` donne les permissions qu'un membre détient dans une équipe, pour les vérifications propres à
l'application.
