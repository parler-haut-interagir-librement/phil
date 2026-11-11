# Extension points

The library is plain PHP: it declares no service, and its classes are final. What an application chooses lives in
data it passes in.

| Point | How |
|---|---|
| The themes and categories | The base list from `BaseCatalogue::load()`, completed, changed or replaced by `CatalogueLoader::apply()` or `applyFile()` with the application's definitions |
| The languages of labels | Any locale in a definition's labels; the fallbacks given to `label()` and `description()` |
| Where warnings are kept | `ContentWarnings::toArray()` gives an array for one JSON column, `fromArray()` reads it back |
| Where preferences are kept | `WarningPreferences::toArray()` and `fromArray()`, in the application's member settings or session |
| The display of warned content for a reader who chose nothing | `WarningPreferences::defaults($display)` |
| Reading themes from another tool | `Catalogue::themes()`, `categories()` and `themesIn()`, `WarningPreferences::themesWith()`, and `Display` — the public API a safety tool reads lines (`Hide`) and veils (`Fold`) from |
| Catching the library's errors | Every exception implements `Phil\ContentWarnings\Exception\ContentWarningsException` |

A stored warning or preference names a theme by key: renaming a key in a definition leaves the data naming the old
one, so a key is migrated in the data before it leaves the catalogue. Removing a theme from the catalogue is safe:
warnings keep it, and display code falls back to its key.
