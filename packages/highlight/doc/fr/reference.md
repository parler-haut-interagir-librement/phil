<!-- phil-dev source-sha256=3ade3b8ba0bf59b90989fa39c434328fcc883cbd9c22c452733909b15f6891a7 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `Phil\Highlight\Highlighter` | `final readonly class` | Les langages par nom et alias ; jetons, HTML, et `highlight()` pour les moteurs de rendu de code |
| `Phil\Highlight\Language` | interface | Un analyseur lexical : nom, alias, jetons |
| `Phil\Highlight\Token` | `final readonly class` | Un morceau de la source et son type |
| `Phil\Highlight\TokenType` | enum | Ce qu'est un jeton ; sa classe HTML |
| `Phil\Highlight\HtmlFormatter` | `final class` | Les jetons en balises span HTML échappées, d'un bloc ou ligne par ligne |
| `Phil\Highlight\LineOptions` | `final readonly class` | Comment un bloc s'écrit ligne par ligne : numéros, lignes mises en évidence, annotations |
| `Phil\Highlight\LineRanges` | `final readonly class` | Des lignes d'un bloc, seules ou en plages, lues de `1,3-5` |
| `Phil\Highlight\LineMark` | enum | Ce qu'une ligne est marquée ; sa classe HTML |
| `Phil\Highlight\Language\Code` | `final readonly class` | L'analyseur lexical d'un langage de programmation décrit par une `Syntax` |
| `Phil\Highlight\Language\Syntax` | `final readonly class` | L'apparence d'un langage de programmation pour `Code` |
| `Phil\Highlight\Language\Quote` | `final readonly class` | Une sorte de texte entre guillemets |
| `Phil\Highlight\Language\Definitions` | `final class` | Les syntaxes fournies |
| `Phil\Highlight\Language\Markup` | `final readonly class` | HTML et XML, avec les langages incorporés |
| `Phil\Highlight\Language\StyleSheet` | `final readonly class` | CSS et SCSS |
| `Phil\Highlight\Language\Yaml` | `final readonly class` | YAML |
| `Phil\Highlight\Language\Ini` | `final readonly class` | L'INI et ses semblables |
| `Phil\Highlight\Language\Dockerfile` | `final readonly class` | Les Dockerfiles, les arguments lus par un langage de shell |
| `Phil\Highlight\Language\Diff` | `final readonly class` | Les diffs et les correctifs |
| `Phil\Highlight\Language\Markdown` | `final readonly class` | Markdown |
| `Phil\Highlight\Language\Twig` | `final readonly class` | Twig, avec les langages de balisage et d'expression |
| `Phil\Highlight\Language\Php` | `final readonly class` | PHP, code et balisage mêlés |
| `Phil\Highlight\Scanner\Cursor` | `final class`, interne | Une position dans une source, lue octet par octet |
| `Phil\Highlight\Scanner\Output` | `final class`, interne | Les jetons joints par type |
| `Phil\Highlight\Exception\HighlightException` | interface | Chaque exception de la bibliothèque |
| `Phil\Highlight\Exception\UnknownLanguage` | `final class` | Un langage demandé par un nom inconnu |
| `Phil\Highlight\Exception\InvalidLanguage` | `final class` | Un langage ajouté sous un nom qui ne peut pas être demandé |
| `Phil\Highlight\Exception\InvalidLineRanges` | `final class` | Des plages de lignes ou un premier numéro de ligne illisibles |

## Fichiers

| Chemin | Rôle |
|---|---|
| `themes/light.css`, `themes/dark.css`, `themes/auto.css` | Les thèmes des classes `hl-` |
