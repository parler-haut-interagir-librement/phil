<!-- phil-dev source-sha256=792ef1e6b26ed38159a4a4fa727e8dfa4671af2a90228dc335d538712900e318 -->
# Valeurs, noms et options

Lisez les cas d'une enum sous forme de primitives. La primitive d'un cas est sa valeur lorsque l'enum est adossée, son
nom lorsqu'elle est pure. Les exemples ci-dessous utilisent deux enums :

```php
enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case SHIPPED = 'shipped';
}

enum Season
{
    case SPRING;
    case SUMMER;
    case AUTUMN;
    case WINTER;
}
```

Chaque section nomme le trait qui ajoute les méthodes ; ajoutez une ligne `use` pour celui-ci dans l'enum.

## `AsInvocableEnumTrait` : un cas sous forme de sa primitive

Un cas peut être appelé pour obtenir sa primitive, et l'enum peut être appelée par le nom d'un cas pour obtenir la même
chose : du code qui a besoin d'une simple valeur n'écrit donc jamais `->value`.

```php
use Phil\Enums\Traits\AsInvocableEnumTrait;

OrderStatus::PAID();      // 'paid'
Season::WINTER();         // 'WINTER'

$status = OrderStatus::SHIPPED;
$status();                // 'shipped'
```

C'est utile là où PHP n'accepte que des primitives, comme clés de tableau par exemple :

```php
$deliveryDays = [
    OrderStatus::PENDING() => 5,
    OrderStatus::PAID() => 3,
];
```

Un appel statique qui ne nomme aucun cas échoue avec `Exceptions\UndefinedCaseError`, une `Error` formulée comme PHP
formule une constante non définie : `Undefined constant App\OrderStatus::REFUNDED`. L'extension PHPStan connaît l'appel
statique de chaque cas et son type de retour ([PHPStan](phpstan.md)).

## `AsNameableEnumTrait` : `names()`

Les noms des cas, dans l'ordre de déclaration :

```php
OrderStatus::names(); // ['PENDING', 'PAID', 'SHIPPED']
Season::names();      // ['SPRING', 'SUMMER', 'AUTUMN', 'WINTER']
```

## `AsValuableEnumTrait` : `values()`

Les primitives des cas, dans l'ordre de déclaration : les valeurs d'une enum adossée, les noms d'une enum pure.

```php
OrderStatus::values(); // ['pending', 'paid', 'shipped']
Season::values();      // ['SPRING', 'SUMMER', 'AUTUMN', 'WINTER']
```

## `AsSelectableEnumTrait` : `options()`

Les cas sous forme de choix. Une enum adossée donne chaque valeur par nom ; une enum pure, qui n'a pas de valeur, donne
la liste de ses noms.

```php
OrderStatus::options(); // ['PENDING' => 'pending', 'PAID' => 'paid', 'SHIPPED' => 'shipped']
Season::options();      // ['SPRING', 'SUMMER', 'AUTUMN', 'WINTER']
```

## `AsStringSelectableEnumTrait` : `stringOptions()`

Un bout de texte par cas, joints en une seule chaîne. Le trait inclut `AsSelectableEnumTrait`.

`stringOptions(?Closure $callback = null, string $glue = "\n")` appelle la fonction de rappel avec le nom de chaque cas
et sa primitive sous forme de chaîne — le nom deux fois pour une enum pure — et joint ce qu'elle renvoie avec le
séparateur :

```php
OrderStatus::stringOptions(static fn (string $name, string $value): string => "$value=$name", '&');
// 'pending=PENDING&paid=PAID&shipped=SHIPPED'
```

Sans fonction de rappel, chaque cas devient un `<option>` HTML, un par ligne. Son libellé est le nom du cas sous forme
de mots : un nom avec des traits de soulignement est découpé sur ceux-ci, un nom en casse mixte avant chaque majuscule,
et un nom tout en majuscules forme un seul mot (`IN_TRANSIT` et `InTransit` se lisent `In transit`).

```php
echo OrderStatus::stringOptions();
// <option value="pending">Pending</option>
// <option value="paid">Paid</option>
// <option value="shipped">Shipped</option>

echo Season::stringOptions();
// <option value="SPRING">Spring</option>
// …
```

**Les options par défaut sont échappées pour le HTML** : un guillemet, `<`, `>` ou `&` dans une valeur ou un libellé est
écrit sous forme d'entité, de sorte qu'une valeur ne peut pas fermer l'attribut ou l'élément. Une fonction de rappel de
votre cru renvoie un texte affiché tel quel : elle échappe donc elle-même ce qu'elle place dans du HTML.
