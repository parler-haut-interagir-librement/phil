# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the library declares and is tested on |
| `ext-json` | any | Reading and writing UJO JSON |
| `ext-mbstring` | any | Counting the characters of a text against its limit |

## Installing

```sh
composer require phil/ujo
```

The library registers nothing and reads no configuration. Every class is a value or a stateless service: construct
`Ujo`, or its parts, where needed, or declare them as services. In a Symfony application, `phil/ujo-bundle` declares
them.
