# Events

Every change runs in one transaction of the store, and its events are dispatched through the PSR-14 dispatcher **once
the transaction is committed**: a listener never hears of a change that was rolled back. When the application wraps a
call in a transaction of its own on the same connection, the events are dispatched when the bundle's part returns.

The events carry identifiers and role names only — never a name, an address or a token —, so that they can go to a
log, an audit trail or a message bus as they are. A listener reads the rest from the store.

| Event | Dispatched when | Carries |
|---|---|---|
| `OrganizationCreated` | An organization is created | organization, first owner |
| `OrganizationRenamed` | It is renamed, its slug changed or not | organization, actor |
| `OrganizationArchived`, `OrganizationRestored` | It is archived, or restored | organization, actor |
| `OrganizationDomainChanged` | Its verified domain is recorded, changed or removed | organization, actor |
| `OrganizationLeftWithoutOwner` | Erasing its last owner left it without any | organization |
| `MemberJoined` | A member joins | organization, member, roles, how (`JoinMethod`), actor when one added them |
| `MemberRolesChanged` | A member's roles change | organization, member, previous roles, roles, actor |
| `MemberLeft` | A member leaves, is removed or erased | organization, member, roles held, why (`LeaveReason`), actor when one removed them |
| `TeamCreated`, `TeamDeleted` | A team is created, or deleted | organization, team, actor |
| `TeamMemberAdded` | A member joins a team | organization, team, member, team roles, actor |
| `TeamMemberRolesChanged` | A member's team roles change | organization, team, member, previous roles, roles, actor |
| `TeamMemberRemoved` | A member leaves a team, or leaves the organization | organization, team, member, team roles held, actor when one removed them |
| `InvitationCreated` | An invitation is created | organization, invitation, actor |
| `InvitationAccepted` | An invitation is used | organization, invitation, member |
| `InvitationRevoked` | An invitation is revoked, or its creator is erased | organization, invitation, actor when one revoked it |
| `ActedOnBehalf` | A member acted on behalf of the organization | organization, real author, action, moment |

They live in `Phil\OrganizationsBundle\Domain\Event`. With Symfony's event dispatcher, a listener is an ordinary one:

```php
#[AsEventListener]
public function __invoke(MemberJoined $event): void
{
    $this->welcome->send($event->memberId, $event->organizationId);
}
```
