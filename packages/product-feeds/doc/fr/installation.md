<!-- phil-dev source-sha256=c1fff7e077b96ab82391976cd3b30aab080982be288c92cff00be422d2fb983d -->
# Installation

```sh
composer require phil/product-feeds
```

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Les constantes de classe typées, l'extension URI |
| `ext-mbstring` | toute | Les longueurs comptées en caractères |
| `phil/money` | ^0.1@dev | Les prix et les prix soldés, écrits à la précision de leur devise |
| `phil/url` | ^0.1.4@dev | Les liens et les images, lus comme le fait un navigateur, quelle que soit la locale du processus, et écrits sous leur forme canonique ; il exige `ext-intl` |

La bibliothèque ne déclare aucun service. Dans une application Symfony, le bundle qui détient les produits implémente
`ProductSource` et publie le flux ; `phil/commerce-catalog-bundle` le fait pour son catalogue.
