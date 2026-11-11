<!-- phil-dev source-sha256=d5fb997f20d0839bf71d1bbaf7b5120628444e7f683e086c7861cf73e19f94b8 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que la bibliothèque déclare et sur lequel elle est testée |
| `ext-intl` | toute | `idn_to_ascii()` et `idn_to_utf8()` : un hôte international gardé sous sa forme ASCII, affiché dans ses propres lettres |
| `ext-mbstring` | toute | Les messages coupés à une longueur en caractères |
| `ext-uri` | toute | `Uri\WhatWg\Url`, l'analyseur d'URL du WHATWG livré avec PHP 8.5 : une adresse lue comme un navigateur la lit |
| `phil/enums` | ^0.3@dev | `EnumSet`, les ensembles de genres qu'un champ accepte |

```sh
composer require phil/url
```

Tant que le paquet n'est pas publié, il est résolu à partir du monodépôt PHIL par un dépôt `path` ou `vcs`.
