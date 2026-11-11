# Extension points

What an application adds or extends. The addresses and kinds are `phil/url`'s.

## The application's own kinds

An enum of the application's implementing `Phil\Url\UrlType` is accepted wherever kinds are named: in the `types` of
`TypedUrlType` and of `UrlOfType`, as an enum class, a case, an `EnumSet` or a list of these. Listed before
`KnownUrlType`, its kinds win detection over the international ones — see [Accepted kinds](kinds.md).

## Forms and validation

| Point | How |
|---|---|
| The kinds a field detects | The `types` option of `TypedUrlType` — see [Forms](forms.md) |
| The kinds accepted | The `types` of `UrlOfType`; its `message` and `typeMessage` — see [Validation](validation.md) |
| More than the field does | A form type extension of the application's on `TypedUrlType` |

## Storage and Twig

| Point | How |
|---|---|
| Where an address is kept | The `phil_url` column type, and the kind in a column of the application's — see [Doctrine](doctrine.md) |
| How an address is shown | `url_display`, `url_type()` and `url_type_label` — see [Twig](twig.md) |
