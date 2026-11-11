# Targeting

An active flag with rules is on for a context when **any** of its rules matches. The rules are tried in their
declared order and the first match gives the reason of the evaluation.

## Attributes

A rule reads one attribute of the `EvaluationContext` (`TargetingAttribute`):

| Attribute | Typical value |
|---|---|
| `tenant` | The account or organisation the request acts for. |
| `project` | The project inside a tenant. |
| `subject` | The person or client making the request. |

The rule, not the context, says which attribute it means: a rollout by tenant keeps every member of a tenant on
the same side of the flag; a rollout by subject splits them.

**A rule that cannot read its attribute does not match.** A context without a tenant never matches a tenant
rule, so a flag fails closed for a context it cannot place. With `AnonymousContextResolver`, every rule fails.

## Membership: `MemberOf`

```yaml
rules:
    - { attribute: tenant, members: [tenant-a, tenant-b] }
```

Matches when the attribute's value is one of the listed identifiers (exact, case-sensitive string comparison).
The set needs at least one member and no empty member: an empty set would be an inactive flag. Reason when it
matches: `TARGETING_MATCH`.

## Percentage rollout: `PercentageRollout`

```yaml
rules:
    - { attribute: tenant, percentage: 20 }
```

Matches a stable share of the values of the attribute. The percentage is a whole number from 0 to 100
(`Percentage`): 0 admits nobody, 100 admits every context that has the attribute. Reason when it matches:
`SPLIT`.

A rule has either `members` or `percentage`, never both.

### The stable bucket

Each value falls into a bucket from 0 to 99 (`Bucket::of()`):

```text
bucket = murmur3a("<flag>:<value>") mod 100
```

`murmur3a` is the 32-bit MurmurHash3 that PHP ships in `ext/hash`, read as an unsigned integer; the input has the
`group:identifier` shape of the Unleash SDKs' gradual rollout. A value is inside the rollout when its bucket is
**strictly below** the percentage (`Percentage::admits()`).

What follows from it:

- **Stable.** The same value gets the same bucket on every request and on every server, so a rollout does not
  flicker.
- **Monotonic.** Raising a percentage only ever adds values: a tenant inside a 20 % rollout stays inside at 50 %.
- **Salted by the flag name.** Two flags place the same tenants independently, so the tenants first in one rollout
  are not the first in all of them. Renaming a flag reshuffles its rollout.
- **Nearly uniform.** `2^32 mod 100 = 96`, so buckets 0 to 95 are one part in about 43 million more likely than
  96 to 99, far below anything a rollout can observe.
- An empty value is refused (every empty key would share one bucket); the context already refuses empty
  attributes.

### A one-way door

**Changing the bucket function reshuffles every running rollout at once.** The unit tests pin known values
(`BucketTest`), for instance:

| Input | Bucket |
|---|---|
| `new-checkout:tenant-1` | 81 |
| `new-checkout:tenant-2` | 62 |
| `other-flag:tenant-1` | 49 |
| `new-checkout:42` | 73 |

A change that moves any of them is deliberate or a bug, never an accident.

## Combining rules

```yaml
flags:
    new-checkout:
        active: true
        rules:
            - { attribute: tenant, members: [tenant-a] }      # the pilot tenant, always
            - { attribute: tenant, percentage: 20 }           # plus a fifth of the others
```

`tenant-a` is on with reason `TARGETING_MATCH`; another tenant is on with reason `SPLIT` if it falls in the 20 %,
otherwise off with reason `DEFAULT`. A context without a tenant is off (`DEFAULT`).

To turn the rollout off for everyone, deactivate the flag (a toggle); the rules stay, and reactivation brings back
the same 20 % ([toggles-and-edits.md](toggles-and-edits.md)).

## Custom rules

`Rule` is an interface (`matches(FlagName, EvaluationContext): bool` and `matchReason(): EvaluationReason`), and
`FlagDefinition` accepts any implementation. A provider of the application can build definitions with its own
rules. They cannot be written to the configuration or the database store, though: `FlagDefinitionFormat` reads and
writes only `MemberOf` and `PercentageRollout`, and refuses to write any other rule
([extension-points.md](extension-points.md#rule)).
