# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Add a condition a rule may name | Implement `Type\ConditionType`, and register it with `ConditionTypes::with()` |
| Give the facts of a subject | Pass `Fact\Facts::of()`, or implement `Fact\FactSource` |
| Offer the types in a builder or an API description | Read `ConditionTypes::describe()` |
| Change a bound | Pass it to `Limits` |

Every implementation keeps nothing from one evaluation to the next, so that one instance serves every request of a
worker.

## Writing a condition type

A condition type has a stable name — lowercase words joined by `.`, `-` or `_`, at most 64 bytes, written in stored
rules and never changed once published —, the schema of its operands and an evaluator that returns a `Verdict`:

```php
use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Fact\FactSource;
use Phil\Rules\Operand\Operand;
use Phil\Rules\Operand\OperandSchema;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Type\ConditionType;

final readonly class CartTotal implements ConditionType
{
    public function name(): string
    {
        return 'cart.total';
    }

    public function operands(): OperandSchema
    {
        return new OperandSchema(Operand::integer('at_least_cents', min: 0), Operand::choice('currency', ['CAD', 'EUR']));
    }

    public function evaluate(Operands $operands, FactSource $facts): Verdict
    {
        $cart = $facts->has('cart') ? $facts->get('cart') : null;

        if (!$cart instanceof Cart) {
            return Verdict::fail('cart.missing');
        }

        $holds = $cart->currency === $operands->string('currency') && $cart->totalCents >= $operands->int('at_least_cents');

        return $holds ? Verdict::pass('cart.enough') : Verdict::fail('cart.too-low', ['currency' => $cart->currency]);
    }
}
```

The operands arrive checked: an evaluator reads them through the typed accessors of `Operands` and never validates them
again. A type reads facts only through the source it is given, calls no rule in turn, and keeps its details to
identifiers and codes rather than personal values.

## Operand kinds

| Constructor | Kind | Kept as |
|---|---|---|
| `Operand::text()` | UTF-8 without control characters, bounded in bytes | `string` |
| `Operand::identifier()` | a letter or digit, then letters, digits, `_`, `.`, `:`, `/` or `-`; 128 bytes | `string` |
| `Operand::integer()` | an integer, between optional bounds | `int` |
| `Operand::number()` | a finite number | `float` |
| `Operand::boolean()` | `true` or `false` | `bool` |
| `Operand::choice()` | one of the listed texts | `string` |
| `Operand::date()` | a calendar date `YYYY-MM-DD` | `string` |
| `Operand::scalar()` | a text, an integer, a finite number or a boolean | as given |
| `Operand::identifierList()` | a non-empty list of distinct identifiers | `list<string>` |
| `Operand::scalarList()` | a non-empty list of distinct scalars | `list<scalar>` |

Each takes `required: false` for an optional operand; the lists take `maxItems` (100 by default, 1 000 at most) and the
texts `maxLength` (255 bytes by default, 10 000 at most).

## Builders and APIs

`ConditionTypes::describe()` lists every type with its operands — name, kind, whether required, choices and bounds — so
that a visual builder or an API description offers exactly what the reader accepts.
