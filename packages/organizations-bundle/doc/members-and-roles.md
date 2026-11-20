# Members and roles

## Members

A member is named by `MemberId`, the opaque subject identifier of the application's user provider: 1 to 128 ASCII
letters, digits, `_`, `.` or `-`, starting with a letter or a digit. An identifier with `@` is refused, so that an
application whose user identifier is the email address finds out at once rather than write addresses into tables, logs
and events. The bundle knows nothing else of a member — no name, no address —, and works with any user provider.

```php
$member = MemberId::fromString($user->getId());
```

## Organizations

`Organizations` creates and changes them:

```php
$acme = $organizations->create('Acme Guild', $member);                 // slug "acme-guild", $member owns it
$other = $organizations->create('Acme', $member, slug: 'acme-hq');      // a slug given is checked, never fixed
$organizations->rename($acme->id, $actor, 'Acme Builders', 'acme-builders');
$organizations->archive($acme->id, $actor);
$organizations->restore($acme->id, $actor);
$organizations->find('acme-builders');                                  // by identifier or slug
```

A name is UTF-8 text, trimmed, 1 to 200 characters, without control characters. A slug is made from the name by the
slugger and numbered — `acme-2` to `acme-20` — when it is taken; a slug given is used as given, or refused with
`SlugTaken`. Its creator is the organization's first owner.

**An archived organization is read only**: its members keep `organization.view`, those who may archive it keep
`organization.archive` to restore it, and every other change throws `OrganizationArchived`. A member may still leave it.

## Roles and permissions

Each membership holds one to sixteen roles. Three are built in:

| Role | Permissions |
|---|---|
| `owner` | Every permission, built in or declared |
| `admin` | `organization.view`, `organization.manage`, `members.invite`, `members.manage`, `teams.manage`, `units.manage`, `organization.act_on_behalf` |
| `member` | `organization.view` |

The built-in permissions are the constants of `Permission`:

| Permission | Allows |
|---|---|
| `organization.view` | Seeing the organization and its members |
| `organization.manage` | Renaming it, changing its slug and its verified domain |
| `organization.archive` | Archiving and restoring it |
| `members.invite` | Creating and revoking invitations |
| `members.manage` | Adding members, changing their roles, removing them |
| `owners.manage` | Giving and taking the owner role |
| `teams.manage` | Creating and deleting teams, managing the members of any team |
| `units.manage` | Creating, renaming, moving, archiving and restoring any unit, managing the members of any unit; every unit permission in every unit — see [Units](units.md) |
| `organization.act_on_behalf` | Acting on behalf of the organization |

The configuration declares other roles, and adds permissions to `admin` and `member`:

```yaml
phil_organizations:
    roles:
        billing: { permissions: [billing.read, billing.pay] }
        member: { permissions: [wiki.read] }
```

Permissions are configuration, reviewed with the code; the roles each member holds are data. An application checks a
declared permission like a built-in one, through `OrganizationAccess::can()` or the voter.

## Changing members

`Members` adds, changes and removes members:

```php
$members->add($acme->id, $actor, $member, ['admin']);
$members->changeRoles($acme->id, $actor, $member, ['member', 'billing']);
$members->remove($acme->id, $actor, $member);       // also from the organization's teams
$members->leave($acme->id, $member);
```

The actor holds `members.manage`, and **grants, changes or removes only roles whose permissions they hold
themselves**, on members whose roles are within their own: an admin neither names an owner, demotes one nor removes
one. A role the configuration does not declare throws `UnknownRole`; a change beyond the actor's permissions throws
`PermissionDenied`; a member added twice throws `AlreadyMember`.

## The last owner

**The last owner can never leave, be removed or lose the owner role**: the change throws `LastOwner`, whose message
names the rule. Each change reads the organization locked until its transaction ends, so that two owners demoting each
other at once run one after the other, and one of them is refused.

The one exception is a right of the person: erasing the personal data of the last owner removes their membership, and
`OrganizationLeftWithoutOwner` is dispatched for an operator to name a new owner. See
[Integrations](integrations.md).

**At least two owners are recommended**, never required: the second one names a new owner when the first is gone.
`OwnershipCheck` tells how many owners an organization has and lists those below the recommendation;
`phil:organizations:show` warns and `phil:organizations:check-owners` lists them, failing when one has none.

## Checking access

`OrganizationAccess` answers for the current state:

```php
$access->can($acme->id, $member, 'billing.pay');
$access->hasRole($acme->id, $member, 'owner');
$access->permissions($acme->id, $member);           // sorted; read only in an archived organization
```

In a controller or a template, the voter answers the same questions: see [Integrations](integrations.md#the-voter).
