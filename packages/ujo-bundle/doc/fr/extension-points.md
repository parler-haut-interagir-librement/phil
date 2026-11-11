<!-- phil-dev source-sha256=73dbf8d63156de1d10f610263f593ae3de56e10ec5727311fa6a39b1e3871148 -->
# Points d'extension

Ce qu'une application configure, redéfinit ou décore. Les types, les moteurs de rendu et les filtres sont ceux de
`phil/ujo`.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `allow_raw` | Si le type `raw` est dans le registre, son HTML assaini |
| `max_bytes`, `max_depth`, `max_blocks`, `max_html_length`, `max_raw_length` | Les bornes de ce qui est lu |

Voir [Configuration](configuration.md).

## Services

Les registres et les moteurs de rendu sont des valeurs immuables : une application redéfinit le service avec une
fabrique à elle qui part de la valeur par défaut, et chaque autre service, `Ujo` en premier, prend par référence celui
qui est redéfini. Voir [Configuration](configuration.md#services).

| Service | Comment une application l'étend |
|---|---|
| `Phil\Ujo\Type\BlockTypeRegistry` | Ses propres types de blocs : `BlockTypeRegistry::default()->with(...)` |
| `Phil\Ujo\Html\HtmlRenderer` | Ses propres moteurs de rendu de blocs avec `with()`, un surligneur de code avec `withCodeHighlighter()`, des passes sur le HTML avec `withFilter()` ; d'autres bundles le décorent pour ajouter les leurs, et les décorations se composent |
| `Phil\Ujo\Normalizer` | Les réglages et l'assainisseur en ligne de ses documents |
| `Phil\Ujo\Text\PlainText` | Ses propres conversions en texte |
| `Phil\Ujo\Ujo` | Injecté partout où des documents sont lus ou rendus ; public |

## Formulaires et validation

| Point | Comment |
|---|---|
| Le modèle d'un champ | L'option `document` de `UjoType` : un `Document` ou son JSON canonique |
| Les types de blocs acceptés | Les `types` de `ValidUjo` ; un bloc d'un autre type, blocs imbriqués compris, est une violation |
| Les messages | Le `invalid_message` du champ, le `message` de la contrainte |

Voir [Formulaires et validation](forms-and-validation.md).
