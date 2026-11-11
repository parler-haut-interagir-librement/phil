# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later, with the filter extension | The floor the library declares and is tested on |
| `symfony/http-client` | ^8.2, optional | The geocoding adapters |
| `symfony/cache` | ^8.2, optional | `CachedGeocoder` |

```sh
composer require phil/geo
```

Until the package is published, it is resolved from the PHIL monorepo through a `path` or `vcs` repository.
