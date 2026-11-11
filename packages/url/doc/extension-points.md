# Extension points

The library is plain PHP and declares no service. What an application adds is kinds of address.

## `UrlType`

`Phil\Url\UrlType` — a kind of address is a case of an enum implementing it. See [Kinds of address](kinds.md).

| Method | Contract |
|---|---|
| `accepts(Url $url): bool` | Whether the address, already normalised, is of that kind; reads its host and path through `Url`, never fetches it |

An enum of the application's own — a region's business registry, its government's portal — declares its most
specific cases first: detection takes the first case that accepts.

## Detection

| Point | How |
|---|---|
| Which kinds are accepted | The `EnumSet`s given to `UrlTypes::detect()` and `UrlTypes::type()`: an application's own kinds, `KnownUrlType`, or a few cases of either |
| Which kind wins | The order of the sets: the application's before `KnownUrlType`, so that its kinds win over `Website` and over `Mastodon`'s reading of `/@name` |
| Refusing an address of no listed kind | Leave out the catch-all `Website` |

A kind is kept by its enum class and case wherever an address is stored with it: renaming or removing a case the
data still name is a migration of the data.
