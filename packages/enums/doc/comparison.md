# Comparison

`AsComparableEnumTrait` compares a case with other values **by identity**: a case equals itself and nothing else —
not its value, not its name, not a case of another enum that happens to share them.

```php
use Phil\Enums\Traits\AsComparableEnumTrait;

enum Priority: int
{
    use AsComparableEnumTrait;

    case LOW = 1;
    case NORMAL = 2;
    case URGENT = 3;
}
```

## One value

| Method | True when |
|---|---|
| `is(mixed $enum)` | the value is this very case |
| `isNot(mixed $enum)` | the value is anything else |

```php
Priority::URGENT->is(Priority::URGENT); // true
Priority::URGENT->is(3);                // false: a value is not its case
Priority::LOW->isNot(Priority::NORMAL); // true
```

## Several values

| Method | True when |
|---|---|
| `in(iterable $targets)` | the case is among the values; an iterator is read only until it is found |
| `notIn(iterable $targets)` | the case is not among them |
| `equalsOneOf(array $enums)` | the case is in the array |
| `notEqualsOneOf(array $enums)` | the case is not in the array |

```php
$escalated = [Priority::NORMAL, Priority::URGENT];

Priority::URGENT->in($escalated);            // true
Priority::LOW->notIn($escalated);            // true
Priority::LOW->equalsOneOf($escalated);      // false
Priority::NORMAL->notEqualsOneOf([Priority::LOW]); // true
```

## Membership of the enum

| Method | True when |
|---|---|
| `has(mixed $target)` | the value is one of the enum's cases |
| `doesntHave(mixed $target)` | it is none of them |

```php
Priority::has(Priority::LOW); // true
Priority::has('LOW');         // false
Priority::doesntHave(2);      // true
```
