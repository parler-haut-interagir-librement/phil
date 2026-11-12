# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the library declares and is tested on |
| `ext-mbstring` | any | Texts cut to a length in characters |
| `phil/recurrence` | ^0.1@dev | `Weekday`, the days of the week |

```sh
composer require phil/almanac
```

The library has no configuration and keeps nothing: every date is computed when it is asked for. With Symfony,
`phil/almanac-bundle` reads definitions from configuration and from the database.
