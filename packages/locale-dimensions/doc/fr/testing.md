<!-- phil-dev source-sha256=f29ca18758108e702b7487ac8b97cb06cf9cadf6d5d499c9dbcbb66e9b9a9702 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Locale` | Les graphies acceptées et refusées, la langue et la région, l'étiquette de langue, les langues partagées, l'égalité |
| `Version` | La première version, la suivante, un numéro enregistré relu, zéro et les nombres négatifs refusés, l'ordre et l'égalité |
| `FallbackChain` | La locale demandée, la résolution dans l'ordre de la chaîne, une chaîne épuisée, un changement de langue, les refus |
| `DeclaredLocales` | L'ordre de déclaration, les chaînes, les locales servies et non servies, chaque déclaration mal formée |

La suite s'exécute sans noyau, sans base de données et sans réseau. À la racine du monodépôt :

```sh
vendor/bin/phpunit packages/locale-dimensions/tests
```
