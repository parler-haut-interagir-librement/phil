# PHPStan

Two traits add methods PHPStan cannot see on its own: `AsInvocableEnumTrait` makes `Status::DRAFT()` a static
call, and `AsMetadatableEnumTrait` makes `Status::DRAFT->label()` an accessor. The library ships one extension for
each, and a rule for `#[RequiredMeta]`, all loaded by `extension.neon`:

```neon
includes:
    - vendor/phil/enums/extension.neon
```

With [`phpstan/extension-installer`](https://github.com/phpstan/extension-installer), the `extra.phpstan` entry of
the library's `composer.json` includes it automatically.

## What the extensions type

- **`InvocableEnum\ReflectionExtension`**: a static call named after a case of an enum that uses
  `AsInvocableEnumTrait` returns the case's primitive — its value for a backed enum, its name for a pure one.
- **`MetadatableEnum\ReflectionExtension`**: an accessor named after a meta property of an enum that uses
  `AsMetadatableEnumTrait` exists, and returns the meta property's value type. The meta properties are read where
  the runtime reads them: the `#[Meta]` attribute of the enum, then that of each trait the enum uses. The return
  type is the property's `TValue`, joined with the type of its `defaultValue()` only when some case of the enum
  does not carry the attribute and can therefore fall back to it.

## What the rules report

- **`RequiredMeta\RequiredMetaRule`**: each case of an enum that does not carry a meta property its
  `#[RequiredMeta]` attributes ask for, one error per case and property, with the identifier
  `philEnums.requiredMeta` — `Case App\Status::PUBLISHED does not carry #[Phil\Enums\Attribute\Label], which the
  enum requires.` The errors are reported on the enum's declaration.
- **`Declaration\EnumDeclarationRule`**: what an enum's attributes declare wrongly, each with its identifier:

  | Identifier | Reports |
  |---|---|
  | `philEnums.defaultCase` | More than one case marked `#[DefaultCase]`. |
  | `philEnums.ambiguousAlias` | An `#[Alias]` that is another case's value or name, or that two cases share: `parse()` never reaches it. |
  | `philEnums.negativeWeight` | A `#[Weight]` below 0. |
  | `philEnums.notBitFlags` | A case of a `#[BitFlags]` enum whose value is not an integer power of two. |
  | `philEnums.requiredMetaUndeclared` | A property `#[RequiredMeta]` asks for that `#[Meta]` does not declare, on an enum with `AsMetadatableEnumTrait`: it has no accessor. |

- **`Ordinal\StoredOrdinalRule`** (`philEnums.storedOrdinal`): `ordinal()` called on an enum with
  `AsOrdinalEnumTrait` inside `jsonSerialize()`, `__serialize()`, `normalize()` or `convertToDatabaseValue()` —
  where a value leaves the process. An ordinal changes when the cases are reordered; store the value. It is a
  heuristic: it knows these methods by name and follows no data flow.

The extensions and the rule read attributes by reflection and never instantiate a case, since PHPStan forbids autoloading
during analysis.
