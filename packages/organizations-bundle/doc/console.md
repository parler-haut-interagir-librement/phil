# Console

With `symfony/console`, the bundle registers four commands. They show identifiers, slugs, names and roles, nothing
else of a member.

## `phil:organizations:list`

```sh
bin/console phil:organizations:list --status=active --limit=50 --after=acme
```

The organizations by slug, a page at a time: identifier, slug, name, status and verified domain. `--status` is
`active` or `archived`, both when omitted; `--limit` is 1 to 1000, 100 by default; `--after` the slug the page starts
after.

## `phil:organizations:show`

```sh
bin/console phil:organizations:show acme-guild
```

An organization by identifier or slug: its name, status, creation and domain, how many members, owners and teams it
has, how many invitations can still be used, and its owners — with a warning when it has fewer owners than
`recommended_owners`. It fails on an unknown organization.

## `phil:organizations:check-owners`

```sh
bin/console phil:organizations:check-owners --limit=100
```

The organizations with fewer owners than recommended, those without any first. It **fails when one has no owner at
all** — its last owner was erased —, so that a scheduled run alerts the operator; fewer owners than recommended is a
warning only.

## `phil:organizations:purge-invitations`

```sh
bin/console phil:organizations:purge-invitations --retention=30
```

Deletes the invitations that expired or were revoked more than the retention ago, in days, `purge_retention` by
default. Run it each day from the scheduler or cron.
