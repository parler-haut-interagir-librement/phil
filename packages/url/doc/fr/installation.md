<!-- phil-dev source-sha256=95d16fbd4096a259692487d8f06d26e7f2a82adb61f0411b819a6ebae1a3924d -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que la bibliothèque déclare et sur lequel elle est testée |
| `ext-intl` | toute | `idn_to_ascii()` et `idn_to_utf8()` : un hôte international gardé sous sa forme ASCII, affiché dans ses propres lettres |
| `ext-mbstring` | toute | Les messages coupés à une longueur en caractères |
| `phil/enums` | ^0.3@dev | `EnumSet`, les ensembles de genres qu'un champ accepte |

```sh
composer require phil/url
```

Tant que le paquet n'est pas publié, il est résolu à partir du monodépôt PHIL par un dépôt `path` ou `vcs`.
