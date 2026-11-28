# Conditions

The rule of a restricted content is a rule of `phil/rules`, read on a registry that holds the condition types of
access:

```php
use Phil\ContentAccess\Condition\AccessConditions;
use Phil\Rules\Json\RuleReader;

$reader = new RuleReader(AccessConditions::types());          // beside the built-in comparisons
$reader = new RuleReader(AccessConditions::types($registry)); // beside a registry the application built
```

## The facts

The decider builds the facts of each decision with `AccessFacts::for()`. The library sets these names; an application
that passes a fact under one of them gets `ReservedFact`, so that no caller stands in for the viewer or the clock.

| Fact | Holds |
|---|---|
| `viewer.id` | the viewer's stable identifier, or `null` |
| `viewer.roles` | the viewer's roles, a list of identifiers |
| `viewer.email_domain` | the domain of the viewer's verified email address, or `null` |
| `viewer.ip` | the network address of the request, or `null` |
| `viewer.grants` | the viewer's grants, read from `Entitlements` only when a condition asks for them |
| `now` | the instant of the decision |

The application adds its own facts as the third argument of `decide()`: a lesson completed, a score, a field of the
profile, given as values or as closures computed the first time a condition reads them. The generic comparisons of
`phil/rules` read them: `fact.equals`, `fact.in`, `fact.contains`, `fact.compare`, `fact.present` and `date.compare`.

## `access.member`

The viewer is signed in. No operand.

| Reason | When |
|---|---|
| `access.member` | the viewer is signed in |
| `access.anonymous` | the viewer is a visitor |

## `access.role`

The viewer holds at least one of the roles, compared exactly.

| Operand | Kind | |
|---|---|---|
| `roles` | identifier list | required |

| Reason | Details |
|---|---|
| `access.role-held` | `role`: the first role of the operand the viewer holds |
| `access.role-missing` | — |

## `access.feature`

The viewer holds an active grant of the feature: from its start, included, to its end, excluded.

| Operand | Kind | |
|---|---|---|
| `feature` | identifier, such as `tier:gold` | required |
| `after_days` | integer, 0 to 36 500 | optional: the content opens that many days after the grant started |
| `for_days` | integer, 1 to 36 500 | optional: the content closes that many days after the grant started |

Days are calendar days in the time zone of the grant's start. `after_days` opens a content by seniority — the third
lesson a week after enrolment —; `for_days` gives a rolling duration of access — a year from the purchase. A fixed end
of access is a `date.compare` on the fact `now`:

```json
{"all": [
    {"type": "access.feature", "operands": {"feature": "course:intro", "for_days": 365}},
    {"type": "date.compare", "operands": {"fact": "now", "operator": "before", "date": "2027-06-30"}}
]}
```

| Reason | Details |
|---|---|
| `access.feature-held` | `feature` |
| `access.feature-not-yet` | `feature`, `opens_at`: the earliest instant an active grant opens it, in ATOM form |
| `access.feature-elapsed` | `feature`: an active grant held it past `for_days` |
| `access.feature-missing` | `feature`: no active grant of the feature |
| `fact.type` | `fact`, `expected`: the instant or the grants are not of their type |

When several grants of the feature are active, one that opens the content is enough; otherwise the content not yet open
is reported before the one past its duration.

## `access.institution`

The viewer belongs to an institution, by the verified domain of their email address — the domain itself or one of its
subdomains — or by the network address of the request inside a declared range.

| Operand | Kind | |
|---|---|---|
| `domains` | list of host names, 100 at most, such as `library.example` | optional |
| `networks` | list of IPv4 or IPv6 addresses with an optional prefix, 100 at most, such as `192.0.2.0/24` | optional |

The application passes the email domain to `Viewer::member()` only once it verified the address. A domain is compared in
lowercase, without a leading `@` or a trailing dot, its internationalized labels in their `xn--` form;
`evillibrary.example` does not match `library.example`.

| Reason | Details |
|---|---|
| `access.institution-domain` | — |
| `access.institution-network` | — |
| `access.institution-none` | — |
| `access.institution-empty` | — : neither domains nor networks are given |
| `access.institution-invalid` | `operand`, `index`: a declared domain or range is malformed, and the condition fails |

The verdict names how the viewer matched, never the domain or the address, so that a trace may be logged.
