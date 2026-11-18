# Configuration

The bundle's configuration lives under `phil_organizations`. Only `invitations.hash_key` is required.

```yaml
# config/packages/phil_organizations.yaml
phil_organizations:
    store: dbal                    # dbal, memory, or the id of an OrganizationStore service
    connection: default            # the DBAL connection of the tables
    clock: clock                   # a PSR-20 clock, symfony/clock's by default
    slugger: ~                     # a phil/slug Slugger; a transliterating slugger when null
    member_of_user: ~              # a MemberOfUser; the user identifier when it is a stable identifier
    recommended_owners: 2
    roles:
        billing: { permissions: [billing.read, billing.pay] }
        admin: { permissions: [billing.read] }       # adds to the built-in admin
    team_roles:
        reviewer: { permissions: [review.approve] }
    invitations:
        hash_key: '%env(ORGANIZATIONS_INVITATION_KEY)%'
        default_lifetime: 7        # days
        max_lifetime: 30           # days
        default_uses: 1
        max_uses: 100
        purge_retention: 30        # days
    domain_join:
        enabled: false
        roles: [member]
    access:
        enabled: ~                 # null: whenever phil/access-bundle is installed
        organization_kind: organization
        team_kind: team
```

| Option | Default | What it does |
|---|---|---|
| `store` | `dbal` | Where organizations are kept: `dbal`, the six tables of a DBAL connection; `memory`, for tests; or the id of a service implementing `OrganizationStore`. |
| `connection` | `default` | The DBAL connection of the tables. |
| `clock` | `clock` | The PSR-20 clock every moment is read from. |
| `slugger` | `null` | The `phil/slug` `Slugger` that makes slugs from names; a `TransliteratingSlugger` when null. |
| `member_of_user` | `null` | The `MemberOfUser` the voter asks which member a signed-in user is; the user identifier, when it is a stable identifier, by default. |
| `recommended_owners` | `2` | The owners recommended per organization: fewer is a warning of `phil:organizations:show` and `phil:organizations:check-owners`, never refused. |
| `roles` | `{}` | The roles of an organization besides `owner`, `admin` and `member`, each with its permissions; listed under `admin` or `member`, the permissions add to those built-in roles. `owner` cannot be declared: it holds every permission. |
| `team_roles` | `{}` | The roles of a team besides `maintainer` and `member`, each with its permissions; `organization` cannot be a team role. |
| `invitations.hash_key` | required | The keys of the HMAC-SHA-256 each token is kept as, at least 32 bytes each: one key, or a list whose first key hashes new tokens and whose others still find the invitations made under them, up to eight. A later configuration file replaces the list rather than adding to it. |
| `invitations.default_lifetime` | `7` | The days an invitation lasts when its creator gives no expiry. |
| `invitations.max_lifetime` | `30` | The most days an invitation may last, at least `default_lifetime`. |
| `invitations.default_uses` | `1` | The uses an invitation allows when its creator says nothing. |
| `invitations.max_uses` | `100` | The most uses an invitation may allow, at least `default_uses`, at most 10,000. |
| `invitations.purge_retention` | `30` | The days an expired or revoked invitation is kept before `phil:organizations:purge-invitations` deletes it. |
| `domain_join.enabled` | `false` | Whether a member whose verified address is in an organization's verified domain may join it. |
| `domain_join.roles` | `[member]` | The roles a member joins with by the domain; never `owner`. |
| `access.enabled` | `null` | Whether memberships and roles are published as relations and tuples of `phil/access-bundle`: `null` publishes them whenever that bundle is registered. |
| `access.organization_kind` | `organization` | The kind of the organizations in the access model. |
| `access.team_kind` | `team` | The kind of the teams in the access model. |

A role is 1 to 64 lowercase letters, digits or `_`, starting with a letter; a permission is 1 to 128 lowercase letters,
digits, `_`, `.` or `-`, starting with a letter. The configuration is refused when one is out of shape.
