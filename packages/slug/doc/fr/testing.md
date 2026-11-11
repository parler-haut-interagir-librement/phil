<!-- phil-dev source-sha256=698c93bb54813a7f143c52b1656db36dbc6814027bd6eb82f5364ab11171dc38 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Slug`, `InvalidSlug` | Chaque règle et chaque borne, l'égalité, la graphie redonnée |
| `TransliteratingSlugger` | Les accents, les ligatures, les apostrophes, les chiffres, la locale, la coupure à un mot et ses bornes, un texte dont on ne peut rien faire |
| `LeadingWordsRemoved` | Un mot retiré une fois, un slug d'un seul mot gardé, le slug du générateur décoré, les mots vérifiés, avec ou sans générateur |
| `Sluggers`, `UnknownSlugger` | Par nom et par défaut, les noms, un nom inconnu et une valeur par défaut inconnue |

```sh
vendor/bin/phpunit packages/slug/tests
```
