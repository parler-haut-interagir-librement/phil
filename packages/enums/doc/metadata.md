# Metadata

`AsMetadatableEnumTrait` attaches values to cases with attributes — a label, a colour, a weight — and reads them back
through accessors named after them.

## Declaring metadata on an enum

A **meta property** is an attribute class extending `Meta\AbstractMetaProperty`. The enum lists those it enables in
`#[Meta(...)]`, and each case carries the ones it has values for:

```php
use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Traits\AsMetadatableEnumTrait;

#[Meta(Label::class, Badge::class)]
enum Ticket: string
{
    use AsMetadatableEnumTrait;

    #[Label('Open')] #[Badge('amber')]
    case OPEN = 'open';

    #[Label('Resolved')] #[Badge('teal')]
    case RESOLVED = 'resolved';

    #[Label('Spam')]
    case SPAM = 'spam';
}
```

`Label` ships with the library; `Badge` is the application's own:

```php
use Attribute;
use Phil\Enums\Meta\AbstractMetaProperty;

/** @extends AbstractMetaProperty<string> */
#[Attribute]
final class Badge extends AbstractMetaProperty
{
}
```

## Reading it

Each enabled meta property gives the cases an accessor: the short class name with a lower-case first letter.

```php
Ticket::OPEN->label();   // 'Open'
Ticket::RESOLVED->badge(); // 'teal'
Ticket::SPAM->badge();   // null: the case carries no Badge, and Badge's default value is null
```

**A method no enabled meta property names fails** with `BadMethodCallException`, worded as PHP's own error —
`Call to undefined method App\Ticket::lable()` —, so a misspelled accessor, or one whose meta property was removed
from `#[Meta]`, never passes for a missing value. The PHPStan extension reports it as an unknown method before it
runs ([PHPStan](phpstan.md)).

## Writing a meta property

A subclass of `AbstractMetaProperty` may override three things.

| Override | Effect | Example |
|---|---|---|
| `transform(mixed $value): mixed` | Rewrites the value as the attribute is built. | `'amber'` stored as `'badge-amber'` |
| `defaultValue(): mixed` (static) | What a case without the attribute answers; `null` unless overridden. Narrowing its return type (`string`) narrows the accessor's for PHPStan. | `'grey'` |
| `customMethodName(): ?string` (static) | The accessor's name, when the class name should not give it. | `'colour'` gives `->colour()` |

```php
#[Attribute]
final class Badge extends AbstractMetaProperty
{
    public static function defaultValue(): string
    {
        return 'badge-grey';
    }

    protected function transform(mixed $value): mixed
    {
        return 'badge-'.$value;
    }

    protected static function customMethodName(): ?string
    {
        return 'colour';
    }
}

Ticket::OPEN->colour(); // 'badge-amber'
Ticket::SPAM->colour(); // 'badge-grey'
```

`method()` gives the accessor's name, as the trait and the PHPStan extension compute it.

## Finding a case by its metadata

`fromMeta()` and `tryFromMeta()` take an instance of the meta property — built with the static `make()`, which
transforms the value as the attribute would — and give the first case carrying that value:

```php
Ticket::fromMeta(Label::make('Resolved'));  // Ticket::RESOLVED
Ticket::tryFromMeta(Label::make('Closed')); // null
Ticket::fromMeta(Label::make('Closed'));    // ValueError
```

### Meta properties declared by a trait

A trait the enum uses may carry its own `#[Meta(...)]`. Its meta properties are read after the enum's, both at
runtime and by the PHPStan extension, so a trait can bring an attribute and its accessor to every enum that uses it:

```php
#[Meta(Shade::class)]
trait HasShade
{
}

#[Meta(Label::class)]
enum Theme
{
    use AsMetadatableEnumTrait;
    use HasShade;

    #[Label('Dark')] #[Shade('black')]
    case DARK;
}

Theme::DARK->label(); // 'Dark'
Theme::DARK->tint();  // 'black', when Shade names its accessor `tint` through customMethodName()
```

### Read once

The meta properties of an enum, and the values each case carries, are read on the first access and kept for the
rest of the process: attributes cannot change while the code runs. An accessor, `fromMeta()`, `tryFromMeta()` or
`whereMeta()` called again reflects nothing and instantiates no attribute. Only meta property attributes are
instantiated, never another attribute a case carries. Of a repeated meta property such as `Group`, the accessor
gives the first value; `Reflection::metaValueList()` gives them all, in declaration order, and an empty list for a
case without it.

### Error messages

`fromMeta()` names the enum, the meta property and the value it looked for: a scalar or `Stringable` value is
written as its string, any other value as its type —
`Enum App\Status does not have a case with a meta property "App\Weight" of value "array"`.

### Included Meta Properties

The package ships with five ready-to-use meta properties:

| Meta property | Value | Repeatable | Used by |
|---|---|---|---|
| `Description` | `string` | No | Your own accessors; an integration may translate it. |
| `Group` | `string` | Yes | `inGroup()` and `groupByMeta()` ([Operations](operations.md)). |
| `Label` | `string` | No | Your own accessors; an integration may translate it. |
| `Alias` | `string` | Yes | `parse()` ([Order, parsing and defaults](order-and-parsing.md)). |
| `Weight` | `int`, 0 or more, 1 by default | No | `random()` ([Operations](operations.md)). |

Three attributes are not meta properties — they carry no value per case, and have no accessor: `#[DefaultCase]`
and `#[TransitionsTo]` on cases ([Order, parsing, defaults and transitions](order-and-parsing.md)), and `#[BitFlags]`
on an enum ([Sets and maps](sets-and-maps.md)).

### Required metadata

`#[RequiredMeta]` names the meta properties every case of the enum must carry, such as a `Label` for each case a
form shows. It is repeatable.

```php
use Phil\Enums\Attribute\RequiredMeta;

#[Meta(Label::class, Description::class)]
#[RequiredMeta(Label::class)]
enum Status: string
{
    use AsMetadatableEnumTrait;

    #[Label('Draft')]
    case DRAFT = 'draft';

    case PUBLISHED = 'published'; // reported: no #[Label]
}
```

Two checks read it. **The PHPStan extension** reports each case that misses one, with the identifier
`philEnums.requiredMeta` ([PHPStan](phpstan.md)). **`Reflection::missingRequiredMeta()`** lists them at run time,
by case name, for a test in a project without PHPStan ([Testing](testing.md)):

```php
Reflection::missingRequiredMeta(Status::class); // ['PUBLISHED' => [Label::class]]
```

A required property every case carries also types its accessor without `null`, since no case falls back to the
default value.

### Typing accessors without the PHPStan extension

The accessors are answered by `__call()`, which an editor cannot see. With PHPStan, the extension types them. To help
an editor as well, declare them with `@method` on the enum, or on a trait that several enums use with the same meta
property:

```php
/**
 * @method string      label()
 * @method string|null badge()
 */
#[Meta(Label::class, Badge::class)]
enum Ticket: string
{
    use AsMetadatableEnumTrait;
    // …
}
```
