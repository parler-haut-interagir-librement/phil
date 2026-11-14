<!-- phil-dev source-sha256=3a41c5f192b3fa451241899452fa0a19922a1db9b7b5fdf8fa051d93dec32c3e -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Locale` | Les graphies acceptées et refusées, une locale lue de l'extérieur, la langue et la région, l'étiquette de langue, les langues partagées, l'égalité |
| `Version` | La première version, la suivante, un numéro enregistré relu, zéro et les nombres négatifs refusés, l'ordre et l'égalité |
| `FallbackChain` | La locale demandée, la résolution dans l'ordre de la chaîne, une chaîne épuisée, un changement de langue, les refus |
| `DeclaredLocales` | L'ordre de déclaration, les chaînes, les chaînes par défaut et leurs replis par défaut, les locales servies et non servies, chaque déclaration mal formée |

La suite s'exécute sans noyau, sans base de données et sans réseau. À la racine du monodépôt :

```sh
vendor/bin/phpunit packages/locale-dimensions/tests
```
