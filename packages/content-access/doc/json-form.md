# JSON form

A policy is stored in a versioned JSON form, read by `Json\AccessPolicyReader` and written by
`Json\AccessPolicyWriter`:

```json
{
    "version": 1,
    "audience": "restricted",
    "rule": {"version": 1, "condition": {"type": "access.feature", "operands": {"feature": "tier:gold"}}},
    "public_from": "2027-01-15T00:00:00+00:00",
    "preview": true,
    "when_denied": "locked"
}
```

| Member | | |
|---|---|---|
| `version` | required | `1` |
| `audience` | required | `public`, `members` or `restricted` |
| `rule` | for `restricted` only | a rule in the versioned form of `phil/rules` |
| `public_from` | optional | an RFC 3339 instant with a numeric offset |
| `preview` | optional | `true` or `false`, `false` by default |
| `when_denied` | optional | `locked`, by default, or `hidden` |

```php
use Phil\ContentAccess\Condition\AccessConditions;
use Phil\ContentAccess\Json\AccessPolicyReader;
use Phil\ContentAccess\Json\AccessPolicyWriter;
use Phil\Rules\Json\RuleReader;

$reader = new AccessPolicyReader(new RuleReader(AccessConditions::types()));
$policy = $reader->fromJson($stored);       // or fromArray() for an API body already decoded

$writer = new AccessPolicyWriter();
$writer->toJson($policy);
$writer->fingerprint($policy);
```

## What is refused

`InvalidPolicy` is thrown, with the JSON Pointer of the faulty member, for:

- a document larger than the reader's `maxBytes` (65 536 by default), refused before it is decoded;
- a document that is not JSON, or nested deeper than 128 levels;
- anything but an object, and any member not in the table;
- a version other than `1`;
- an unknown audience, a `preview` that is not a boolean, an unknown `when_denied`;
- an instant without a numeric offset, with `Z`, with fractions of a second, or of a day that does not exist;
- a restricted policy without a rule object, a rule on any other audience;
- a rule refused by the reader of `phil/rules`, its exception kept as the previous one — an unknown condition type, a
  refused operand, a rule past the reader's limits;
- the contradictions refused when a policy is built (see [policies](policies.md)).

## The canonical form

The writer gives the members in the order of the table, the rule in the canonical form of `phil/rules`, the instant in
UTC, and always writes `preview` and `when_denied`. A canonical form reads back to itself, and `fingerprint()` is the
SHA-256 of it in hexadecimal: two policies that mean the same have the same fingerprint.
