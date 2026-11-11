<!-- phil-dev source-sha256=1a109b2fdd5f9077f8e15077ef296352fcdef0d166215369bd4ebbb750d7d4cb -->
# Validation

`UrlOfType` vérifie qu'une valeur (un texte, un `Url` ou un `TypedUrl`) est une adresse Web de l'un des genres
acceptés. `null` et le texte vide sont valides ; ajoutez `NotBlank` pour en exiger une.

```php
#[UrlOfType([KnownUrlType::Facebook, KnownUrlType::Instagram])]
public ?string $social = null;

#[UrlOfType(RegistryUrlType::class)]
public ?string $registry = null;
```

| Option | Valeur par défaut |
|---|---|
| `types` | Chaque genre international ; voir [kinds.md](kinds.md) |
| `message` | *This value is not a valid web address.*, code `UrlOfType::INVALID_ERROR` |
| `typeMessage` | *This address is not one of the accepted kinds: {{ types }}.*, code `UrlOfType::NOT_ACCEPTED_ERROR` |

Un `TypedUrl` est vérifié par son genre : une adresse de genre `Website` est refusée par une contrainte qui n'accepte
que Facebook, même quand l'adresse est sur Facebook.
