# Metadata and expiry

Every flag can say what it guards, who owns it, what kind of flag it is and the day it should be gone
(`FlagMetadata`). **None of it changes an evaluation.**

```yaml
flags:
    new-checkout:
        active: true
        description: The one-page checkout.
        owner: checkout-team
        kind: release
        expires_on: '2027-03-31'
```

## Fields

| Field | Type | Constraint |
|---|---|---|
| `description` | `?string` | Valid UTF-8, trimmed, at most 500 characters (`FlagMetadata::DESCRIPTION_MAX_LENGTH`). Blank means none. |
| `owner` | `?string` | Valid UTF-8, trimmed, at most 100 characters (`FlagMetadata::OWNER_MAX_LENGTH`). Blank means none. Free text: a team, a person, a channel. |
| `kind` | `?FlagKind` | `release`, `experiment`, `ops` or `permission`. |
| `expires_on` | `?DateTimeImmutable` | Written `YYYY-MM-DD`. Only the date counts, read in UTC (midnight UTC of that day). |

The trimming of `description` and `owner` is PHP's `trim()` (ASCII white space), unlike reasons and actors,
which are trimmed of every Unicode blank.

## Kinds

The four categories of Pete Hodgson's article *Feature Toggles* (martinfowler.com). The kind says how long a flag
should live and who owns it:

| Kind | For | Lifetime |
|---|---|---|
| `release` | Ships a feature before it is released. | Removed once the feature is on for everyone. |
| `experiment` | Splits contexts between variants to compare them. | Removed when the experiment concludes. |
| `ops` | Lets an operator turn a feature off in production. | Most are retired once the feature is trusted. |
| `permission` | Opens a feature to a set of tenants, projects or subjects, such as beta testers. | May live long. |

The force-open flag the bundle declares for each circuit of `phil/circuit-breaker-bundle` is of kind `ops` ([circuit-breakers.md](circuit-breakers.md)).

## Expiry

`FlagMetadata::isExpiredAt(DateTimeImmutable $now): bool` is true from midnight UTC of the expiry day: a flag
that expires on the 31st is expired from the 31st at 00:00 UTC. A flag without an expiry date never expires.

**A flag is inventory.** Each one doubles the paths through the code it guards, and the expiry date is what lets
a build fail on a flag that has outstayed its purpose. Expiry has no effect on evaluation: an expired flag keeps
working exactly as before, and only the listing and the admin say it is expired.

- `bin/console phil:feature-flags:list --fail-on-expired` exits with a failure while any flag is past its date —
  run it in CI ([console.md](console.md));
- the admin list and the flag page mark an expired flag ([admin.md](admin.md));
- a test of the application can read `FlagCatalog::entries()` and fail on `declared->metadata->isExpiredAt()`
  ([testing.md](testing.md)).

The listing and the admin judge expiry on the **declared** metadata of the providing definition, against the
configured clock.

## YAML dates

An unquoted `expires_on: 2027-03-31` is parsed by YAML as a timestamp; the configuration tree converts it back to
the UTC date, so both forms work. Quoting keeps the intent obvious.

## Where metadata lives

Metadata belongs to the definition, so it comes from the provider that declares the flag: the configuration, or
the database store where the admin edits it in fields of its own. The state store holds only the active state;
toggling a flag never touches its metadata.
