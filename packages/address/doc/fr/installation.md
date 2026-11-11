<!-- phil-dev source-sha256=7386d43ceb3db90f42ddb92143fbaceb9febc4754289884d3741ca2c7c834118 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare la bibliothèque et sur lequel elle est testée |
| `ext-mbstring` | toute version | Champs mesurés, majuscules et comparaisons dans toute écriture |
| `symfony/intl` | ^8.2 | Codes et noms de pays |

```sh
composer require phil/address
```

Les formats et les subdivisions sont fournis avec la bibliothèque, dans `data/`.
