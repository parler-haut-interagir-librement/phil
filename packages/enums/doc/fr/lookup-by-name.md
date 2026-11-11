<!-- phil-dev source-sha256=460076e32a2d96a8d35a536a309b43e5cd0c4710d577783bc7ab66acb5fbfe9a -->
# Recherche par nom

`AsFromambleEnumTrait` trouve un cas par son nom. Chaque enum reçoit `fromName()` et `tryFromName()`. Une enum pure, à
laquelle PHP ne donne pas de `from()`, reçoit aussi `from()` et `tryFrom()` comme cette même recherche ; une enum
adossée garde les `from()` et `tryFrom()` propres à PHP, qui lisent ses valeurs.

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

| Méthode | Sur | Répond | Lorsqu'aucun cas n'a ce nom |
|---|---|---|---|
| `fromName(string $case)` | toute enum | le cas qui a ce nom | lève `ValueError` |
| `tryFromName(string $case)` | toute enum | le cas qui a ce nom | `null` |
| `from(int\|string $case)` | une enum pure | le cas qui a ce nom | lève `ValueError` |
| `tryFrom(int\|string $case)` | une enum pure | le cas qui a ce nom | `null` |

```php
Unit::fromName('LITRE');   // Unit::LITRE
Unit::from('l');           // Unit::LITRE: PHP's from(), by value
Unit::tryFromName('l');    // null: 'l' is a value, not a name

Coin::from('HEADS');       // Coin::HEADS
Coin::tryFrom('EDGE');     // null
Coin::fromName('heads');   // ValueError: names are compared exactly, letter case included
```

Le message du `ValueError` nomme l'entrée et l'enum : `"EDGE" is not a valid name for enum App\Coin`. Pour une recherche
qui accepte aussi les valeurs, les alias ou une autre casse, utilisez `parse()` ([Ordre, analyse, valeurs par défaut et
transitions](order-and-parsing.md)).
