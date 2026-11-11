# Values, names and options

Read the cases of an enum as primitives. The primitive of a case is its value when the enum is backed, its name when
it is pure. The examples below use two enums:

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

Each section names the trait that adds the methods; add a `use` line for it in the enum.

## `AsInvocableEnumTrait`: a case as its primitive

A case can be called to get its primitive, and the enum can be called by a case's name to get the same thing, so
that code that needs a plain value never writes `->value`.

```php
use Phil\Enums\Traits\AsInvocableEnumTrait;

OrderStatus::PAID();      // 'paid'
Season::WINTER();         // 'WINTER'

$status = OrderStatus::SHIPPED;
$status();                // 'shipped'
```

It helps where PHP takes only primitives, as array keys for instance:

```php
$deliveryDays = [
    OrderStatus::PENDING() => 5,
    OrderStatus::PAID() => 3,
];
```

A static call that names no case fails with `Exceptions\UndefinedCaseError`, an `Error` worded as PHP words an
undefined constant: `Undefined constant App\OrderStatus::REFUNDED`. The PHPStan extension knows each case's static
call and its return type ([PHPStan](phpstan.md)).

## `AsNameableEnumTrait`: `names()`

The case names, in declaration order:

```php
OrderStatus::names(); // ['PENDING', 'PAID', 'SHIPPED']
Season::names();      // ['SPRING', 'SUMMER', 'AUTUMN', 'WINTER']
```

## `AsValuableEnumTrait`: `values()`

The case primitives, in declaration order: the values of a backed enum, the names of a pure one.

```php
OrderStatus::values(); // ['pending', 'paid', 'shipped']
Season::values();      // ['SPRING', 'SUMMER', 'AUTUMN', 'WINTER']
```

## `AsSelectableEnumTrait`: `options()`

The cases as choices. A backed enum gives each value by name; a pure enum, which has no value, gives the list of its
names.

```php
OrderStatus::options(); // ['PENDING' => 'pending', 'PAID' => 'paid', 'SHIPPED' => 'shipped']
Season::options();      // ['SPRING', 'SUMMER', 'AUTUMN', 'WINTER']
```

## `AsStringSelectableEnumTrait`: `stringOptions()`

One piece of text per case, joined into one string. The trait includes `AsSelectableEnumTrait`.

`stringOptions(?Closure $callback = null, string $glue = "\n")` calls the callback with each case's name and its
primitive as a string — the name twice for a pure enum — and joins what it returns with the glue:

```php
OrderStatus::stringOptions(static fn (string $name, string $value): string => "$value=$name", '&');
// 'pending=PENDING&paid=PAID&shipped=SHIPPED'
```

Without a callback, each case becomes an HTML `<option>`, one per line. Its label is the case name as words: a name
with underscores is split on them, a name in mixed case before each capital, and a name all in capitals is one word
(`IN_TRANSIT` and `InTransit` read `In transit`).

```php
echo OrderStatus::stringOptions();
// <option value="pending">Pending</option>
// <option value="paid">Paid</option>
// <option value="shipped">Shipped</option>

echo Season::stringOptions();
// <option value="SPRING">Spring</option>
// …
```

**The default options are escaped for HTML**: a quote, `<`, `>` or `&` in a value or a label is written as an
entity, so a value cannot close the attribute or the element. A callback of your own returns text that is printed as
it is, so it escapes what it puts into HTML itself.
