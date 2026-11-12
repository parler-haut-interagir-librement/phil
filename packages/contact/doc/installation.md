# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the library declares and is tested on |
| `ext-filter` | any | Checking email addresses |
| `ext-intl` | any | Email domains in other scripts, checked in their ASCII form; the language of a name's culture |
| `ext-mbstring` | any | Texts measured in characters; vCard lines folded between characters |
| `phil/phone`, `phil/url`, `phil/instant-messenger` | ^0.1@dev | Phone numbers, web addresses, messaging handles |
| `phil/address` | ^0.1@dev | The postal addresses of a JSContact card |
| `phil/enums` | ^0.3@dev | The sets of messaging services and kinds of address the JSContact reader accepts |

```sh
composer require phil/contact
```
