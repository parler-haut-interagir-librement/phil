# phil/content-access

**Decide who reads a content — public, members, tiers by rules, early access then public, drip and rolling access,
institutions — and never send the reserved part to whoever may not read it.**

phil/content-access is a plain PHP library. A content carries an access policy; a decider tells, for a viewer at an
instant, whether they read the whole content, its public part before the cut, a locked entry that says what remains,
or nothing at all. Blogs, publications, courses, calendars, media and subscriptions read the same policies instead of
each writing its own paywall.

- **Rights are feature names**: a content asks for `tier:gold` or `course:intro`; grants come from subscriptions,
  memberships, purchases, enrolments or an external platform through one port, `Entitlements`, read only when a rule
  asks for a feature and never for a visitor who is not signed in.
- **Rules, not code**: a restricted content holds a rule of `phil/rules` — nested all, any and not, bounded and
  explained — with four condition types of access: `access.member`, `access.role`, `access.feature` (with an opening
  by seniority and a rolling duration) and `access.institution` (a verified email domain or a declared network range),
  beside the generic comparisons and the application's own facts.
- **Early access then public**: a policy may become public for good at an instant; the decision says when, so that a
  cache keeps nothing past it.
- **Nothing reserved leaves the server**: `PreviewCut` gives the blocks of a UJO (Universal JSON Output) document the
  decision lets through — the whole content, the part before the cut, or nothing —; a document without a cut has no
  public part.
- **No oracle**: a hidden content is answered as a missing one; a personal decision is never cached for others.
- **Search engines told the truth**: `StructuredData` gives `isAccessibleForFree` and `hasPart` for the reserved part,
  the same for every viewer.
- **No tracking**: no counter of articles read, no metered wall; an institution matches without its domain or address
  being kept or logged.

## Status

| Part | State |
|---|---|
| Policies: audiences, early access, preview, locked or hidden denial | Implemented and tested |
| The decider, its decisions and their explanation | Implemented and tested |
| The condition types `access.member`, `access.role`, `access.feature`, `access.institution` | Implemented and tested |
| Grants and the `Entitlements` port, in memory and chained | Implemented and tested |
| The cut of a UJO document and the schema.org properties | Implemented and tested |
| The versioned JSON form: reader, writer, fingerprint | Implemented, tested and fuzzed |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, a first decision. |
| [Policies and decisions](doc/policies.md) | Audiences, early access, preview, denial, decisions, caching, the cut, structured data. |
| [Conditions](doc/conditions.md) | The facts a rule reads and the four condition types of access. |
| [JSON form](doc/json-form.md) | The versioned form of a policy, what is refused, the fingerprint. |
| [Extension points](doc/extension-points.md) | Grant sources, the application's facts, condition types, what is stable. |
| [Testing](doc/testing.md) | The tests, the fuzz target, the benchmark. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/content-access
```

```php
use Phil\ContentAccess\AccessDecider;
use Phil\ContentAccess\AccessPolicy;
use Phil\ContentAccess\Condition\AccessConditions;
use Phil\ContentAccess\PreviewCut;
use Phil\ContentAccess\Viewer;
use Phil\Rules\Json\RuleReader;

$rule = new RuleReader(AccessConditions::types())->fromJson('{"version": 1, "condition": {"any": [
    {"type": "access.feature", "operands": {"feature": "tier:gold"}},
    {"type": "access.institution", "operands": {"domains": ["library.example"]}}
]}}');

$policy = AccessPolicy::restricted($rule)
    ->withPublicFrom(new DateTimeImmutable('2027-01-15T00:00:00+00:00'))
    ->withPreview();

$decision = new AccessDecider($entitlements, $clock)->decide($policy, Viewer::member($user->id(), $user->roles()));

$decision->access;                                            // Access::Full, Preview, Locked or Hidden
$blocks = new PreviewCut()->visibleBlocks($decision, $document['blocks']);
```

## Requirements

PHP 8.5 or later, `phil/rules`, `psr/clock` and `symfony/http-foundation` (for the comparison of network ranges).

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
