# Teams

A team belongs to one organization: a name, a slug unique within the organization, and its members, each a member of
the organization with one or more **team roles**. Two are built in, and the configuration declares others:

| Team role | Permissions |
|---|---|
| `maintainer` | `team.manage` |
| `member` | none |

```yaml
phil_organizations:
    team_roles:
        reviewer: { permissions: [review.approve] }
```

## Managing teams

```php
$design = $teams->create($acme->id, $actor, 'Design');
$teams->addMember($design->id, $actor, $member, ['maintainer']);
$teams->changeRoles($design->id, $actor, $member, ['member', 'reviewer']);
$teams->removeMember($design->id, $actor, $member);     // the member themselves leaves
$teams->rename($design->id, $actor, 'Product design', 'product-design');
$teams->delete($design->id, $actor);
```

A member holding `teams.manage` in the organization creates, renames and deletes teams and manages the members of any
team, with every team role. A member whose team roles grant `team.manage` — a maintainer — renames that team and manages
its members, granting, changing or removing only team roles whose permissions they hold in the team. Only members of
the organization join its teams; a member who leaves the organization, or is removed or erased, leaves its teams too.

`Teams::permissions()` gives the permissions a member holds in a team, for the application's own checks.
