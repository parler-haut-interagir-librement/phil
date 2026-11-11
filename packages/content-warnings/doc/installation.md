# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the library declares and is tested on |
| `ext-json` | any | Catalogue definitions in JSON files |
| `ext-mbstring` | any | Labels and notes measured in characters |

```sh
composer require phil/content-warnings
```

The library declares no service and reads no configuration: an application builds its catalogue once, keeps it, and
passes it where warnings are matched. In a Symfony application, `phil/content-warnings-bundle` does that from its
configuration.
