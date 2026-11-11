<!-- phil-dev source-sha256=7a6fcc62edb7499c5788d9ff33038ffefbb72af7e54251beb10adf1f78a21269 -->
# Référence

| Type | Sorte | Rôle |
|---|---|---|
| `Phil\Typography\Typographer` | `final readonly class` | Corrige des fragments HTML, des documents et du texte brut avec ses correcteurs ; `for()` construit le préréglage d'une locale |
| `Phil\Typography\Typographers` | `final class` | Un typographe par locale, construit à la première utilisation, configurable par noms de correcteurs |
| `Phil\Typography\Preset` | `final class` | Les correcteurs d'une locale, par nom et dans l'ordre |
| `Phil\Typography\Locale` | `final readonly class` | Une langue, une écriture et une région, et ses candidates |
| `Phil\Typography\Text\Run` | `final readonly class` | Les nœuds texte d'un bloc, en parties ; `map()`, `replace()`, `characters()` |
| `Phil\Typography\Text\Chars` | `final class` | Les caractères qu'écrivent les correcteurs, et la classe des espaces qu'ils remplacent |
| `Phil\Typography\Fixer\Fixer` | interface | Une règle typographique |
| `Phil\Typography\Fixer\UnicodeNormalization` | `final readonly class` | La forme de normalisation C |
| `Phil\Typography\Fixer\Ellipsis` | `final readonly class` | Trois points, des points de suspension |
| `Phil\Typography\Fixer\Dimension` | `final readonly class` | Le signe de multiplication entre des mesures |
| `Phil\Typography\Fixer\Dash` | `final readonly class` | Tirets cadratins et demi-cadratins, intervalles, tirets entre des mots |
| `Phil\Typography\Fixer\DashStyle` | enum | Demi-cadratin entouré d'espaces ou cadratin collé, selon la locale |
| `Phil\Typography\Fixer\SmartQuotes` | `final readonly class` | Les guillemets de la langue |
| `Phil\Typography\Fixer\CurlyQuote` | `final readonly class` | L'apostrophe |
| `Phil\Typography\Fixer\NoSpaceBeforeComma` | `final readonly class` | Aucune espace avant une virgule |
| `Phil\Typography\Fixer\SpaceBeforePunctuation` | `final readonly class` | Les espaces du français |
| `Phil\Typography\Fixer\FrenchSpacing` | enum | Les règles de la France ou du Canada, selon la locale |
| `Phil\Typography\Fixer\Trademark` | `final readonly class` | ™, ®, © et ℗ |
| `Phil\Typography\Fixer\Unit` | `final readonly class` | Une espace insécable entre un nombre et son unité |
| `Phil\Typography\Fixer\Hyphen` | `final readonly class` | Les traits d'union conditionnels |
| `Phil\Typography\Fixer\WordBreak` | `final readonly class` | Des espaces de largeur nulle entre les mots thaïs |
| `Phil\Typography\Quote\QuoteStyle` | `final readonly class` | Les guillemets principaux et intérieurs |
| `Phil\Typography\Quote\QuoteStyles` | `final class` | Les guillemets de chaque locale |
| `Phil\Typography\Hyphenation\Patterns` | `final readonly class` | Les motifs analysés, les exceptions et les longueurs minimales |
| `Phil\Typography\Hyphenation\Hyphenator` | `final readonly class` | L'algorithme de Liang |
| `Phil\Typography\Hyphenation\Dictionary` | `final class` | Les motifs fournis, par locale ou par étiquette, lus une fois ; les expérimentaux (`EXPERIMENTAL`) seulement sur demande |
| `Phil\Typography\Exception\TypographyException` | interface | Toute exception de la bibliothèque |
| `Phil\Typography\Exception\InvalidLocale` | `final class` | Une chaîne qui n'est pas une locale |
| `Phil\Typography\Exception\UnsupportedLanguage` | `final class` | Aucun motif de césure pour une langue |
| `Phil\Typography\Exception\UnknownFixer` | `final class` | Un nom de correcteur que les préréglages ne connaissent pas |

`Phil\Typography\Html\Blocks`, `Phil\Typography\Html\Collector` et `Phil\Typography\Text\Characters` sont internes.
