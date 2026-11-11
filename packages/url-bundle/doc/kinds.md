# Accepted kinds

A field and a constraint name the kinds they accept in any of these forms, alone or in a list:

| Form | Accepts |
|---|---|
| `null` | Every international kind, `KnownUrlType::class` |
| An enum class, `RegistryUrlType::class` | All its cases |
| A case, `KnownUrlType::Instagram` | That kind |
| An `EnumSet` | Its cases |

Cases of one enum make one set; sets keep the order they first appear in, which is the order detection tries them
([phil/url](../../url/doc/kinds.md)). An application's own kinds come before the international ones when listed
first:

```php
['types' => [RegistryUrlType::class, KnownUrlType::class]]
```

Anything else throws an `InvalidArgumentException` when the field or the constraint is used.
