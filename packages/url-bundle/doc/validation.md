# Validation

`UrlOfType` checks that a value — a text, a `Url` or a `TypedUrl` — is a web address of one of the accepted kinds.
`null` and the empty text are valid; add `NotBlank` to require one.

```php
#[UrlOfType([KnownUrlType::Facebook, KnownUrlType::Instagram])]
public ?string $social = null;

#[UrlOfType(RegistryUrlType::class)]
public ?string $registry = null;
```

| Option | Default |
|---|---|
| `types` | Every international kind; see [kinds.md](kinds.md) |
| `message` | *This value is not a valid web address.*, code `UrlOfType::INVALID_ERROR` |
| `typeMessage` | *This address is not one of the accepted kinds: {{ types }}.*, code `UrlOfType::NOT_ACCEPTED_ERROR` |

A `TypedUrl` is checked by its kind: one of `Website` is refused by a constraint accepting only Facebook, even when
the address is on Facebook.
