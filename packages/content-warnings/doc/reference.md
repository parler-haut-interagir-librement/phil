# Reference

| Type | Kind | Role |
|---|---|---|
| `Key` | `final class` | The rule of theme and category keys |
| `LocalizedText` | `final readonly class` | A text by locale, read with fallbacks |
| `Category` | `final readonly class` | A group of themes |
| `Theme` | `final readonly class` | A theme content can be warned for |
| `Catalogue` | `final readonly class` | The themes and categories, in order |
| `CatalogueLoader` | `final class` | Builds a catalogue from a definition, or applies one onto a catalogue |
| `BaseCatalogue` | `final class` | The base list in six languages |
| `Severity` | string-backed enum | How intense a theme is in one piece of content |
| `ContentWarning` | `final readonly class` | One warning: a theme, a severity, a note |
| `ContentWarnings` | `final readonly class` | The warnings of a piece of content, kept as an array |
| `Display` | string-backed enum | Show, fold or hide |
| `Preference` | `final readonly class` | A display from a severity on |
| `WarningPreferences` | `final readonly class` | A reader's preferences, kept as an array |
| `WarningMatcher` | `final readonly class` | Decides for one piece of content and one reader |
| `Decision` | `final readonly class` | The display of a piece of content and the warnings that gave it |
| `Exception\ContentWarningsException` | interface | Every exception of the library |
| `Exception\InvalidCatalogue` | `final class`, `InvalidArgumentException` | A definition, a key or a label that breaks a rule |
| `Exception\InvalidWarning` | `final class`, `InvalidArgumentException` | A warning, a list or a preference that breaks a rule |
| `Exception\UnreadableWarnings` | `final class`, `UnexpectedValueException` | Stored warnings or preferences that cannot be read back |
