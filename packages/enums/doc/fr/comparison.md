<!-- phil-dev source-sha256=c0b09de8f0df10414a9befc0c4096fd6137898b426bc3543b3e89293c33a31df -->
# Comparaison

`AsComparableEnumTrait` compare un cas à d'autres valeurs **par identité** : un cas est égal à lui-même et à rien
d'autre — ni à sa valeur, ni à son nom, ni à un cas d'une autre enum qui se trouverait à les partager.

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

## Une valeur

| Méthode | Vrai lorsque |
|---|---|
| `is(mixed $enum)` | la valeur est ce cas même |
| `isNot(mixed $enum)` | la valeur est n'importe quoi d'autre |

```php
Priority::URGENT->is(Priority::URGENT); // true
Priority::URGENT->is(3);                // false: a value is not its case
Priority::LOW->isNot(Priority::NORMAL); // true
```

## Plusieurs valeurs

| Méthode | Vrai lorsque |
|---|---|
| `in(iterable $targets)` | le cas figure parmi les valeurs ; un itérateur n'est lu que jusqu'à ce qu'il soit trouvé |
| `notIn(iterable $targets)` | le cas n'y figure pas |
| `equalsOneOf(array $enums)` | le cas est dans le tableau |
| `notEqualsOneOf(array $enums)` | le cas n'est pas dans le tableau |

```php
$escalated = [Priority::NORMAL, Priority::URGENT];

Priority::URGENT->in($escalated);            // true
Priority::LOW->notIn($escalated);            // true
Priority::LOW->equalsOneOf($escalated);      // false
Priority::NORMAL->notEqualsOneOf([Priority::LOW]); // true
```

## Appartenance à l'enum

| Méthode | Vrai lorsque |
|---|---|
| `has(mixed $target)` | la valeur est l'un des cas de l'enum |
| `doesntHave(mixed $target)` | elle n'est aucun d'eux |

```php
Priority::has(Priority::LOW); // true
Priority::has('LOW');         // false
Priority::doesntHave(2);      // true
```
