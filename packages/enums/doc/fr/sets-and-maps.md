<!-- phil-dev source-sha256=d2ac02100f53d6bef68cfc4dd1931415018fc10477c9a14a0474be8f8b7819b9 -->
# Ensembles et tables associatives

Deux structures indexées par les cas d'une même enum, ce que les tableaux PHP ne peuvent pas être : `EnumSet`, un
ensemble de cas, et `EnumMap`, une valeur pour chaque cas. Les deux sont immuables et connaissent leur enum même
lorsqu'elles ne contiennent rien.

## EnumSet

Un ensemble contient chaque cas au plus une fois, toujours dans l'ordre de déclaration, et offre les opérations d'un
ensemble plutôt que celles d'une liste : c'est ce que sont des permissions, les jours d'ouverture d'une boutique ou les
canaux qu'une personne accepte.

```php
use Phil\Enums\Collection\EnumSet;

$open = EnumSet::of(Day::class, Day::Monday, Day::Tuesday, Day::Friday);
$weekend = EnumSet::of(Day::class, Day::Saturday, Day::Sunday);

$open->contains(Day::Friday);           // true
$open->union($weekend)->names();        // ['Monday', 'Tuesday', 'Friday', 'Saturday', 'Sunday']
$open->complement()->names();           // ['Wednesday', 'Thursday', 'Saturday', 'Sunday']
$open->without(Day::Friday)->count();   // 2
```

| Méthode | Renvoie |
|---|---|
| `EnumSet::of($enum, ...$cases)` | L'ensemble de ces cas ; un cas d'une autre enum est refusé. |
| `EnumSet::none($enum)`, `EnumSet::all($enum)` | L'ensemble vide, tous les cas. |
| `EnumSet::fromBitmask($enum, $mask)` | L'ensemble que représente un masque de bits. |
| `EnumSet::fromValues($enum, $values)` | L'ensemble des cas que représentent ces valeurs — des noms pour une enum pure —, telles que les envoie un formulaire ou une requête ; une valeur qu'aucun cas n'a est refusée. |
| `contains($case)`, `isEmpty()`, `count()` | S'il contient le cas, s'il n'en contient aucun, combien. |
| `with(...$cases)`, `without(...$cases)` | L'ensemble avec ou sans ces cas. |
| `union($other)`, `intersect($other)`, `diff($other)` | Les cas de l'un ou l'autre, des deux, de celui-ci seulement. |
| `complement()` | Les cas de l'enum que cet ensemble ne contient pas. |
| `equals($other)`, `isSubsetOf($other)`, `isSupersetOf($other)` | Des comparaisons entre ensembles. |
| `cases()` | Les cas, sous forme de `CaseCollection` à filtrer, trier ou transformer ([Opérations](operations.md)). |
| `names()`, `values()`, `toBitmask()` | Les noms, les valeurs — les noms d'une enum pure —, le masque de bits. |
| `enum()` | La classe de l'enum. |

Il est `Countable` et itérable. Une opération entre des ensembles de deux enums lève une `InvalidArgumentException`.

### Indicateurs binaires

Lorsque l'enum est adossée à des entiers qui sont des puissances de deux — les indicateurs de C# et de Python —, un
ensemble entier tient dans un seul entier : les valeurs de ses cas, combinées par un OU binaire.

```php
enum Permission: int
{
    case Read = 1;
    case Write = 2;
    case Delete = 4;
}

EnumSet::of(Permission::class, Permission::Read, Permission::Delete)->toBitmask(); // 5
EnumSet::fromBitmask(Permission::class, 5)->names();                               // ['Read', 'Delete']
```

Marquez une telle enum `#[BitFlags]` : l'extension PHPStan signale alors un cas dont la valeur n'est pas une puissance
de deux, avant que l'exécution ne le fasse ([PHPStan](phpstan.md)).

`toBitmask()` et `fromBitmask()` lèvent une `LogicException` pour une enum qui a un cas qui n'est pas un bit — pure,
adossée à des chaînes, 0, ou un entier qui n'est pas une puissance de deux —, et `fromBitmask()` lève une
`InvalidArgumentException` pour un masque qui contient un bit qu'aucun cas ne représente. **Ne renumérotez jamais un
indicateur** une fois des masques stockés : les valeurs sont le format de stockage.

## EnumMap

Une table associative contient une valeur pour chaque cas : la couleur de chaque statut, le prix de chaque forfait. Son
itération donne les cas comme clés.

```php
use Phil\Enums\Collection\EnumMap;

$colours = EnumMap::total(Status::class, static fn (Status $status): string => match ($status) {
    Status::DRAFT => 'grey',
    Status::PUBLISHED => 'green',
    Status::ARCHIVED => 'brown',
});

$colours->get(Status::PUBLISHED); // 'green'

foreach ($colours as $status => $colour) {
    // $status is a Status case
}
```

**Totale par construction.** `total()` appelle la fonction de rappel pour chaque cas, et un `match` sans branche
`default` fait lever par PHP — et signaler par PHPStan — le cas qu'il oublie : un cas ajouté à l'enum échoue là où la
table est créée, et non sous la forme d'une clé manquante le jour où un enregistrement l'atteint. `fromArray()` lit une
table à partir d'un tableau indexé par les valeurs, tel que l'écrit une configuration, et refuse un tableau auquel il
manque un cas ou qui a une clé qui n'est pas un cas, en nommant les deux.

```php
EnumMap::fromArray(Status::class, ['draft' => 'grey', 'published' => 'green', 'archived' => 'brown']);
EnumMap::fromPartial(Status::class, ['published' => 'green'], 'grey');  // draft and archived are grey
```

`fromPartial()` garde la table totale tout en rendant le choix explicite : un cas oublié prend la valeur par défaut que
vous avez écrite, et non une clé manquante.

| Méthode | Renvoie |
|---|---|
| `EnumMap::total($enum, $callback)` | La table de ce que la fonction de rappel donne à chaque cas. |
| `EnumMap::fromArray($enum, $values)` | La table d'un tableau indexé par les valeurs des cas, leurs noms pour une enum pure. |
| `EnumMap::fromPartial($enum, $values, $default)` | La même chose, chaque cas que le tableau laisse de côté prenant la valeur par défaut ; une clé qui n'est pas un cas est toujours refusée. |
| `get($case)` | La valeur du cas. |
| `with($case, $value)` | La même table avec cette valeur pour le cas. |
| `map($callback)` | La table de ce que la fonction de rappel fait de chaque valeur ; elle reçoit la valeur et le cas. |
| `keys()`, `values()` | Les cas sous forme de `CaseCollection`, les valeurs sous forme de liste. |
| `toArray()` | Les valeurs indexées par les valeurs des cas : ce que `fromArray()` relit. |
| `enum()`, `count()` | La classe de l'enum, le nombre de cas. |

`get()` et `with()` lèvent une `InvalidArgumentException` pour un cas d'une autre enum.

## Coût

Les enums sont petites : les structures gardent donc de simples listes plutôt que des index. Mesuré sur PHP 8.5 avec
OPcache, pour une enum de cinq cas : `contains()` prend environ 0,13 µs, `union()` environ 2 µs, `fromBitmask()` environ
2,7 µs. Un index par nom n'économiserait rien qui vaille son code à ces tailles.
