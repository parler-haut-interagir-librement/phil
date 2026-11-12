# Installation

| Requirement | Constraint | Why |
|---|---|---|
| PHP | `>=8.5` | The declared minimum, the version the library is tested on. |

```bash
composer require phil/api-contract:^0.1@dev
```

The library depends on nothing but PHP: headers and bodies are passed in as strings. In a Symfony application,
require `phil/api-contract-bundle` instead: it requires the library.
