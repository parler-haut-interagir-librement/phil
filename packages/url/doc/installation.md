# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the library declares and is tested on |
| `ext-mbstring` | any | Messages cut to a length in characters |
| `ext-uri` | any | `Uri\WhatWg\Url`, the WHATWG URL parser bundled with PHP 8.5: an address read as a browser reads it |
| `phil/enums` | ^0.3@dev | `EnumSet`, the sets of kinds a field accepts |
| `symfony/polyfill-intl-idn` | ^1.43 | `idn_to_ascii()` and `idn_to_utf8()` when `ext-intl` is not loaded: an international host kept in its ASCII form |

```sh
composer require phil/url
```

## With or without `ext-intl`

`ext-intl` is suggested, not required. When it is loaded, PHP's own `idn_to_ascii()` and `idn_to_utf8()` run and the
polyfill stays idle; without it, the polyfill takes their place. The results are the same, with two differences:

| | With `ext-intl` | Without `ext-intl` |
|---|---|---|
| A host of lowercase ASCII letters, digits and hyphens, `example.com` | Read and displayed without any IDNA conversion | The same, at the same speed |
| An international host, `bücher.example` | Converted to ASCII by ICU, about 2 µs per address | Converted by the polyfill in PHP, about 15 times slower: some 30 µs per address |
| `display()` of an international host | In its own letters, `bücher.example`, unless ICU's spoof checker finds it suspicious | In ASCII, `xn--bcher-kva.example`: with no spoof checker, no international host is trusted to be shown in its own letters |

An application whose people enter international addresses, or which shows them, loads `ext-intl`.

Until the package is published, it is resolved from the PHIL monorepo through a `path` or `vcs` repository.
