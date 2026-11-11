<!-- phil-dev source-sha256=d9b4ecb179cd5186a20925de7da36751de6d786d8eac49bacd92dd23bcf8b717 -->
# Points d'extension

Ce qu'une application configure, décore ou utilise. Les typographes et les correcteurs sont ceux de
`phil/typography`.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `locales` | Un typographe par locale, son préréglage ou une liste de noms de correcteurs dans leur ordre d'exécution |
| `default_locale` | La locale de `Phil\Typography\Typographer`, et celle des filtres sans sélecteur de locale |
| `hyphenation`, `experimental_hyphenation` | Si les préréglages font la césure, et les motifs expérimentaux servis |
| `protected_tags` | Les éléments dont le texte n'est jamais modifié |
| `ujo` | Si le HTML des documents UJO est corrigé, et dans quelle locale |

Voir [Configuration](configuration.md).

## Services

| Service | Comment une application l'utilise |
|---|---|
| `Phil\Typography\Typographers` | Injecté pour corriger du texte dans n'importe quelle locale ; public |
| `Phil\Typography\Typographer` | Celui de la locale par défaut ; public |
| `phil_typography.typographer.<locale>` | Un par clé de `locales` ; public |
| `Phil\Typography\Hyphenation\Dictionary` | Redéfini par l'application pour lire des motifs qu'elle fournit elle-même, depuis son propre répertoire |
| `phil_typography.ujo_renderer` | Décore `Phil\Ujo\Html\HtmlRenderer` avec un `TypographyHtmlFilter` ; les autres décorations du moteur de rendu se composent avec lui |

## Correcteurs propres à l'application

Un correcteur de l'application, une classe qui implémente `Phil\Typography\Fixer\Fixer`, s'exécute dans un
typographe que l'application construit avec `new Typographer([...])`, les correcteurs du préréglage tirés de
`Preset::fixers()` parmi les siens. Il s'enregistre sous l'identifiant de service de son choix ; les `locales`
configurées ne nomment que les correcteurs fournis.
