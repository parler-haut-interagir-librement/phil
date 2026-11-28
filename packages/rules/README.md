# phil/rules

**Decide prices, promotions, access or audiences with stored rules: typed all/any/not conditions, bounded cost, a trace
of why, versioned JSON, no eval.**

phil/rules is a plain PHP library. A rule is a tree of fixed conditions: `All`, `Any` and `Not` compose, and each leaf
names a condition type a package declares — a stable name, the schema of its operands and an evaluator. Price books,
promotions, content access, audience segments and automations read the same rules without each writing its own
condition language.

- **Typed and closed**: a rule names only registered types and gives only operands their schemas accept. An unknown
  type, a misspelt operand or a value of the wrong kind is refused when the rule is loaded, never found false later.
- **No code in a rule**: no `eval`, no expression language over user input, no SQL. Facts come from a scope the caller
  passes and are read only when a condition asks for them; a costly fact no condition reads is never computed.
- **Bounded cost**: the bytes, the depth, the conditions and the evaluations of a rule are limited, with a typed
  exception past each bound. A rule cannot loop.
- **Explained**: the result carries a trace — every condition evaluated or skipped — and the leaves that decided it,
  with the JSON Pointer of each and a stable reason code.
- **Versioned JSON**: `{"version": 1, "condition": …}`, read strictly and written canonically, so that a stored rule
  reads back to itself and has a fingerprint.

## Status

| Part | State |
|---|---|
| The condition tree: `All`, `Any`, `Not`, `Leaf` | Implemented and tested |
| Condition types: the registry, operand schemas, the six built-in comparisons | Implemented and tested |
| Evaluation with short-circuit, trace and explanation, bounded by `Limits` | Implemented and tested |
| The versioned JSON form: reader, writer, fingerprint | Implemented, tested and fuzzed |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, a first rule. |
| [Rules](doc/rules.md) | The tree, the facts, the built-in types, the trace, the limits. |
| [JSON form](doc/json-form.md) | The versioned form, what is refused, the fingerprint. |
| [Extension points](doc/extension-points.md) | Writing a condition type, providing facts, what is stable. |
| [Testing](doc/testing.md) | The tests, the fuzz target, the benchmark. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/rules
```

```php
use Phil\Rules\Fact\Facts;
use Phil\Rules\Json\RuleReader;
use Phil\Rules\Type\ConditionTypes;

$reader = new RuleReader(ConditionTypes::builtIn());
$rule = $reader->fromJson('{"version": 1, "condition": {"all": [
    {"type": "fact.in", "operands": {"fact": "country", "values": ["CA", "FR"]}},
    {"type": "fact.compare", "operands": {"fact": "orders", "operator": "gte", "value": 2}}
]}}');

$result = $rule->evaluate(Facts::of(['country' => 'CA', 'orders' => static fn (): int => $orders->countFor($customer)]));
$result->passed;        // true or false
$result->explanation(); // the leaves that decided, with their path and reason
```

## Requirements

PHP 8.5 or later with the extension `mbstring`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
