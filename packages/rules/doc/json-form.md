# JSON form

A rule is stored and exchanged as a versioned JSON object:

```json
{
    "version": 1,
    "condition": {
        "all": [
            {"type": "fact.in", "operands": {"fact": "country", "values": ["CA", "FR"]}},
            {"not": {"type": "fact.present", "operands": {"fact": "unsubscribed_at"}}},
            {"any": [
                {"type": "fact.compare", "operands": {"fact": "orders", "operator": "gte", "value": 2}},
                {"type": "date.compare", "operands": {"fact": "joined", "operator": "before", "date": "2026-01-01"}}
            ]}
        ]
    }
}
```

A condition is one of:

| Form | Node |
|---|---|
| `{"all": [ … ]}` | `All`, a non-empty list |
| `{"any": [ … ]}` | `Any`, a non-empty list |
| `{"not": { … }}` | `Not` |
| `{"type": "…", "operands": { … }}` | `Leaf`; `operands` may be left out when the type needs none |

## Reading

`RuleReader::fromJson()` reads the text, `fromArray()` the same form already decoded, such as the body of an API
request. Both refuse with `InvalidRule`:

- anything but `version` and `condition` at the top, a version other than 1;
- a condition that is not an object, that holds more than one form, or any other key;
- an empty or non-list `all` or `any`, operands given as a list;
- a type the registry does not hold, an operand the schema does not declare, a missing required operand, a value the
  operand's kind refuses.

The message names the JSON Pointer of the faulty node. The bounds of `Limits` hold while reading: the bytes before
decoding, the depth of the JSON while decoding, the depth and the conditions while building.

## Writing

`RuleWriter::toJson()` writes the canonical form: operands in the order their schema declares them, numbers of kind
`number` with their decimal point, texts in UTF-8 without escaping slashes. Reading the canonical form gives the same
rule, which writes the same text. `toArray()` gives the same form as arrays.

`RuleWriter::fingerprint()` is the SHA-256 of the canonical form: two rules with the same fingerprint say the same
thing. A list computed from a rule keeps the fingerprint to prove which rule selected it.

## Versions

The version is the version of the form, not of the rule. A later form will come with its own number, and this library
refuses any number it does not read rather than guess.
