# Access groups

An access group gathers members who get the same access: the supporters of a project, the participants of a cohort,
the new members of a site. Each one is declared in the configuration with the entry points it accepts and, when its
memberships end on their own, their duration:

```yaml
phil_access:
    groups:
        supporters: { entry_points: [purchase, invitation], duration: P1M }
        cohort-2027: { entry_points: [bulk, automation], duration: P6M }
        newcomers: { entry_points: [default] }
```

| Entry point | A member comes in |
|---|---|
| `purchase` | by buying a product, an offer, a donation tier |
| `invitation` | by a personal invitation or an invitation link |
| `bulk` | added by an administrator, alone or with many others |
| `automation` | by a rule of the site: an automation, a workflow |
| `default` | as a new member, from their first visit |

`Application\AccessGroups` brings members in and out:

```php
$until = $groups->join('supporters', MemberId::of('01JABC'), EntryPoint::Purchase); // ?DateTimeImmutable
$groups->leave('supporters', MemberId::of('01JABC'));
$groups->joinDefaults(MemberId::of('01JNEW'));     // every group with the default entry point
$groups->groupsOf(MemberId::of('01JABC'));         // list<string>
$groups->members('supporters', limit: 100);        // list<Membership>
```

An entry point the group does not accept is refused. Coming in again starts the duration again: a renewed purchase
extends the access. Joining dispatches `MemberJoinedGroup` and leaving `MemberLeftGroup`, with identifiers only; a
membership that ends on its own raises no event.

A group is then a grantee like a member: an item shared with it, a space whose members or admins it is.
