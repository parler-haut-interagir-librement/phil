# Lookup by name

`AsFromambleEnumTrait` finds a case by its name. Every enum gets `fromName()` and `tryFromName()`. A pure enum, which
PHP gives no `from()`, also gets `from()` and `tryFrom()` as the same lookup; a backed enum keeps PHP's own `from()`
and `tryFrom()`, which read its values.

```php
use Phil\Enums\Traits\AsFromambleEnumTrait;

enum Unit: string
{
    use AsFromambleEnumTrait;

    case GRAM = 'g';
    case LITRE = 'l';
}

enum Coin
{
    use AsFromambleEnumTrait;

    case HEADS;
    case TAILS;
}
```

| Method | On | Answers | When no case has the name |
|---|---|---|---|
| `fromName(string $case)` | every enum | the case with this name | throws `ValueError` |
| `tryFromName(string $case)` | every enum | the case with this name | `null` |
| `from(int\|string $case)` | a pure enum | the case with this name | throws `ValueError` |
| `tryFrom(int\|string $case)` | a pure enum | the case with this name | `null` |

```php
Unit::fromName('LITRE');   // Unit::LITRE
Unit::from('l');           // Unit::LITRE: PHP's from(), by value
Unit::tryFromName('l');    // null: 'l' is a value, not a name

Coin::from('HEADS');       // Coin::HEADS
Coin::tryFrom('EDGE');     // null
Coin::fromName('heads');   // ValueError: names are compared exactly, letter case included
```

The message of the `ValueError` names the input and the enum: `"EDGE" is not a valid name for enum App\Coin`. For a
lookup that also accepts values, aliases or another letter case, use `parse()`
([Order, parsing, defaults and transitions](order-and-parsing.md)).
