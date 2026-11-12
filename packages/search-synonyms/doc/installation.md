# Installation

```sh
composer require phil/search-synonyms
```

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the package declares |
| `ext-mbstring` | any | Phrases in lower case |
| `phil/search-bundle` | optional | A search that reads the synonyms of a store — see [Stores](stores.md) |

The package has no other dependency and needs no configuration: a store is built with its directory, or given to the
application's container as any service.
