# Integrations

## The voter

With `symfony/security-core`, `OrganizationVoter` votes on the permissions and the roles a member holds in an
organization, given as the `Organization`, its `OrganizationId` or the text of its identifier:

```php
$this->denyAccessUnlessGranted('organization:members.invite', $organization);
$this->denyAccessUnlessGranted('organization_role:owner', $organizationId);
```

```twig
{% if is_granted('organization:billing.pay', organization) %}…{% endif %}
```

A permission follows the roles of the catalogue and the read-only rule of an archived organization. Anyone who is not
signed in, not a member, or whose user is no member, is denied, and the vote gives its reason.

The same voter answers `unit:<permission>` and `unit_role:<role>` on a `Unit`, its `UnitId` or the text of its
identifier, the unit roles held above the unit included, as [Role inheritance](role-inheritance.md#the-voter) explains.

The voter asks `MemberOfUser` which member a signed-in user is. By default the user identifier is the member when it is
a stable identifier; an application whose users sign in by email address names its own:

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

When `phil/privacy-bundle` is installed, `OrganizationsPrivacy` is its exporter and its eraser for the module
`organizations`, the data subject being the member identifier:

- the export lists the member's memberships — organization, name, roles, how and when they joined —, their team
  memberships and the invitations they created;
- the erasure removes their memberships and team memberships, revokes the invitations they created that could still be
  used, and forgets them as the creator of the others; the report says `deleted` for the memberships, the roles and the
  team memberships, `pseudonymised` for the invitations. Erasing twice changes nothing more.

A right of the person is never refused for the organization's sake: the last owner is erased too, and
`OrganizationLeftWithoutOwner` is dispatched for an operator to name a new owner; `phil:organizations:check-owners`
fails until one is named. The same erasure is `MemberErasure::erase()`.

## `phil/access-bundle`

When `phil/access-bundle` is registered — or with `access.enabled: true` —, the bundle adds the organizations to its
access model and publishes memberships and roles as tuples of `phil/resource-permissions`, once each change is
committed:

```text
organization: owner, admin, member, billing, … = [member]
team:         organization = [organization]; maintainer, member, reviewer, … = [member]
```

| Change | Tuples |
|---|---|
| A member joins with roles | `organization:<id>#<role>@member:<id>` for each role |
| A member's roles change | the roles taken away deleted, the roles given written |
| A member leaves | the tuples of their roles deleted |
| A team is created | `team:<id>#organization@organization:<id>` |
| A member joins a team, changes roles, leaves it | `team:<id>#<role>@member:<id>`, the same way |
| A team is deleted | every tuple on the team |

The tuples go through the store of `phil/access-bundle`, in the partition of its scope: with `partition: tenant`,
they are written to the active tenant, and a change made outside any tenant fails rather than write them nowhere in
particular. An application builds on them in its own model contributor:

```php
$types['page'] = $types['page']->with([
    'organization' => Rewrite::direct('organization'),
    'editor' => Rewrite::fromRelated('organization', 'admin'),
]);
```

The kinds are named `organization` and `team` unless `access.organization_kind` and `access.team_kind` say otherwise;
a kind already in the model stops the container. Member identifiers follow the rule of `phil/access-bundle`'s
`MemberId`.

## `phil/audit-bundle`

`ActedOnBehalf` and every other event carry the identifiers an audit trail records: a listener appends them to the
trail of `phil/audit-bundle`, the real author beside the organization.
