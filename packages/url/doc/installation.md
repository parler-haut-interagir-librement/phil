# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the library declares and is tested on |
| `ext-intl` | any | `idn_to_ascii()` and `idn_to_utf8()`: an international host kept in its ASCII form, displayed in its own letters |
| `ext-mbstring` | any | Messages cut to a length in characters |
| `ext-uri` | any | `Uri\WhatWg\Url`, the WHATWG URL parser bundled with PHP 8.5: an address read as a browser reads it |
| `phil/enums` | ^0.3@dev | `EnumSet`, the sets of kinds a field accepts |

```sh
composer require phil/url
```

Until the package is published, it is resolved from the PHIL monorepo through a `path` or `vcs` repository.
