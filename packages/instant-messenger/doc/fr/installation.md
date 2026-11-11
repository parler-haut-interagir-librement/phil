<!-- phil-dev source-sha256=2251016814ce7db549226f3140f737859e1dd6de91c25c077f4d16636e90b8c5 -->
# Installation

| Prérequis | Version | Raison |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que la bibliothèque déclare et sur lequel elle est testée |
| `ext-mbstring` | toute | Les messages coupés à une longueur en caractères |
| `phil/enums` | ^0.3@dev | `EnumSet`, les ensembles de services qu'un champ accepte |
| `phil/phone` | ^0.1@dev | La lecture des numéros de téléphone dans le plan de leur pays |
| `phil/url` | ^0.1@dev | La lecture des liens des services |

```sh
composer require phil/instant-messenger
```
