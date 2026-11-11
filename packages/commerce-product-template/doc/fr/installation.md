<!-- phil-dev source-sha256=20e9ea79221d040b57ea2d03147f03c32db7db3e10f0f30bfd28ad8e596e4c48 -->
# Installation

## Prérequis

| Prérequis | Raison |
|---|---|
| PHP 8.5 ou plus récent | Les classes en lecture seule, les constantes typées, `new` sans parenthèses |
| `ext-mbstring` | La longueur des libellés en caractères |
| `phil/money` | Les prix et les suppléments, en unités mineures d'une devise |

## Installer

```sh
composer require phil/commerce-product-template
```

C'est une bibliothèque, pas un bundle : il n'y a rien à enregistrer. Chaque type est une valeur qui ne change jamais une
fois construite, et `VariantPlanner` ne garde aucun état entre deux appels : une seule instance sert donc chaque plan.

Une application Symfony qui stocke des gabarits et des visuels et en génère son catalogue installe
`phil/commerce-product-template-bundle` à côté.
