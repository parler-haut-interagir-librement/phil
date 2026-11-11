# APIs

Two ways to put enums in an API: the serializer writes the structures of `phil/enums` and labelled cases, and
`EnumSchema` describes an enum for an OpenAPI document.

## Serializer

`EnumsNormalizer` is registered when `symfony/serializer` is installed, before Symfony's own enum normalizer.

| Data | Written as |
|---|---|
| `EnumSet` | The list of its values — the names of a pure enum —, in declaration order: `[1, 4]`. |
| `EnumMap` | An object keyed by the values of the cases, each value normalized in turn: `{"draft": "grey", "published": "green"}`; an empty map is `{}`. |
| A case, with the context option `EnumsNormalizer::LABELS` | `{"value": "pending", "label": "En attente", "description": "En attente du paiement."}`. |
| A case, without it | Left to Symfony's normalizer: its value. |

```php
use Phil\EnumsBundle\Infrastructure\Serializer\EnumsNormalizer;

$serializer->serialize($order, 'json', [EnumsNormalizer::LABELS => true]);
```

The label and description are translated when the enum is translatable and the application has a translator, in
the translator's current locale; otherwise they are the case's `Label` — or its name — and `Description`, `null`
without one.

**Reading sets and maps back** needs their enum, which a class name does not carry — `EnumSet<Permission>` is a
PHPDoc type. Name it in the context, on the property with `#[Context]` or in the call:

```php
use Symfony\Component\Serializer\Attribute\Context;

#[Context([EnumsNormalizer::ENUM => Permission::class])]
public EnumSet $permissions;

$serializer->deserialize('[1, 4]', EnumSet::class, 'json', [EnumsNormalizer::ENUM => Permission::class]);
```

**Leave the generic out of the property's PHPDoc.** With a PHPDoc reader installed — `phpstan/phpdoc-parser` or
`phpdocumentor/reflection-docblock` —, Symfony reads `@var EnumSet<Permission>` as a collection of cases, since a set
is iterable; it then denormalizes the cases and, `EnumSet` being neither an interface nor built from an array, hands
the property a plain array instead of asking the bundle. Type the property `?EnumSet` natively, name the enum with
`#[Context]`, and silence PHPStan's `missingType.generics` on that line.

A map's values are kept as decoded, or denormalized to the type `EnumsNormalizer::VALUE_TYPE` names. Without the
enum in the context, reading fails with the serializer's `InvalidArgumentException`; data that names no case — a
value no case has, a map that misses a case — fails with a `NotNormalizableValueException` whose message can be
shown to the user, at the property's path. A case is read by its value, by Symfony's own normalizer.

## JSON Schema

`EnumSchema::of()` gives the schema of an enum as an OpenAPI document describes a property:

```php
use Phil\EnumsBundle\Application\Schema\EnumSchema;

EnumSchema::of(OrderStatus::class);
// [
//     'type' => 'string',
//     'enum' => ['pending', 'in_transit', 'cancelled'],
//     'x-enum-varnames' => ['PENDING', 'IN_TRANSIT', 'CANCELLED'],
//     'x-enum-descriptions' => ['Waiting for payment.', 'The parcel has left.', 'Cancelled'],
//     'default' => 'pending',
// ]
```

- **`type`** is `integer` for an enum backed by integers, `string` otherwise; a pure enum is written by its names.
- **`x-enum-varnames`** and **`x-enum-descriptions`** are the extensions client generators read for the constants'
  names and comments: the case names, and each case's `Description`, else its `Label`, else its name — untranslated,
  since a contract is one document for every client.
- **`default`** is the case marked `#[DefaultCase]`, when there is one among the values.
- **Deprecated cases are left out**: a contract offers what clients may send. `EnumSchema::of($enum, withDeprecated: true)`
  keeps them, for a response that may still hold one.

It is a plain array: merge it into the schema your documentation tool builds for the property.

## API Platform and NelmioApiDoc

With either tool installed, the bundle completes the schema it builds for an enum, so that `EnumSchema` needs no
merging by hand:

| Tool | What the bundle does | Registered |
|---|---|---|
| API Platform | Decorates the property metadata factory around API Platform's own schema factory: a property typed by an enum gets the values — the names of a pure enum, which API Platform does not describe —, `x-enum-varnames`, `x-enum-descriptions` and the `#[DefaultCase]`. A nullable property keeps `null` among its values and types. | With `api-platform/symfony`; removed when API Platform's bundle is not registered |
| NelmioApiDoc | Describes each enum model — NelmioApiDoc gives enums their own component schema — before NelmioApiDoc's own enum describer: the same type, values, extensions and default, pure enums included. | With `nelmio/api-doc-bundle` |

Deprecated cases are left out, as `EnumSchema` leaves them. A property whose schema is declared by hand —
`#[ApiProperty(schema: …)]` — is left as written, as API Platform leaves it. An `EnumSet` property is not described
as a set: give it its schema by hand, an array of the enum's values.
