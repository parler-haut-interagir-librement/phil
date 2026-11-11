# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\Typography\Typographer` | `final readonly class` | Fixes HTML fragments, documents and plain text with its fixers; `for()` builds the preset of a locale |
| `Phil\Typography\Typographers` | `final class` | One typographer per locale, built on first use, configurable by fixer names |
| `Phil\Typography\Preset` | `final class` | The fixers of a locale, by name and in order |
| `Phil\Typography\Locale` | `final readonly class` | A language, script and region, and its candidates |
| `Phil\Typography\Text\Run` | `final readonly class` | The text nodes of one block, in parts; `map()`, `replace()`, `characters()` |
| `Phil\Typography\Text\Chars` | `final class` | The characters the fixers write, and the class of spaces they replace |
| `Phil\Typography\Fixer\Fixer` | interface | One typographic rule |
| `Phil\Typography\Fixer\UnicodeNormalization` | `final readonly class` | Normalization form C |
| `Phil\Typography\Fixer\Ellipsis` | `final readonly class` | Three stops, one ellipsis |
| `Phil\Typography\Fixer\Dimension` | `final readonly class` | The multiplication sign between measures |
| `Phil\Typography\Fixer\Dash` | `final readonly class` | Em and en dashes, ranges, dashes between words |
| `Phil\Typography\Fixer\DashStyle` | enum | Spaced en dash or closed em dash, by locale |
| `Phil\Typography\Fixer\SmartQuotes` | `final readonly class` | Quotation marks of the language |
| `Phil\Typography\Fixer\CurlyQuote` | `final readonly class` | The apostrophe |
| `Phil\Typography\Fixer\NoSpaceBeforeComma` | `final readonly class` | No space before a comma |
| `Phil\Typography\Fixer\SpaceBeforePunctuation` | `final readonly class` | French spaces |
| `Phil\Typography\Fixer\FrenchSpacing` | enum | The rules of France or of Canada, by locale |
| `Phil\Typography\Fixer\Trademark` | `final readonly class` | ™, ®, © and ℗ |
| `Phil\Typography\Fixer\Unit` | `final readonly class` | A no-break space between a number and its unit |
| `Phil\Typography\Fixer\Hyphen` | `final readonly class` | Soft hyphens |
| `Phil\Typography\Fixer\WordBreak` | `final readonly class` | Zero-width spaces between Thai words |
| `Phil\Typography\Quote\QuoteStyle` | `final readonly class` | Primary and inner quotation marks |
| `Phil\Typography\Quote\QuoteStyles` | `final class` | The marks of each locale |
| `Phil\Typography\Hyphenation\Patterns` | `final readonly class` | Parsed patterns, exceptions and minimum lengths |
| `Phil\Typography\Hyphenation\Hyphenator` | `final readonly class` | Liang's algorithm |
| `Phil\Typography\Hyphenation\Dictionary` | `final class` | The shipped patterns, by locale or tag, read once; the experimental ones (`EXPERIMENTAL`) only when asked for |
| `Phil\Typography\Exception\TypographyException` | interface | Every exception of the library |
| `Phil\Typography\Exception\InvalidLocale` | `final class` | A string that is not a locale |
| `Phil\Typography\Exception\UnsupportedLanguage` | `final class` | No hyphenation patterns for a language |
| `Phil\Typography\Exception\UnknownFixer` | `final class` | A fixer name the presets do not know |

`Phil\Typography\Html\Blocks`, `Phil\Typography\Html\Collector` and `Phil\Typography\Text\Characters` are internal.
