# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilPrintPreflightBundle` | `final class` | The bundle: its configuration, the attribute of the checks, the pass of the resolver |
| `Application\PrintProfiles` | `final readonly class` | The printer profiles by name, and the default one |
| `Application\Lengths` | `final class` | Lengths and sizes read from text |
| `Application\Preflighter` | `final readonly class` | The preflight against a profile, with the checks of the application |
| `Application\AsPreflightCheck` | attribute | Registers a check of the application |
| `Application\MeasuredDocument` | `final readonly class` | A document measured in a browser, read from JSON |
| `Application\Port\FontResolver` | interface | The font file behind a family, weight and style |
| `Application\Exception\InvalidMeasurement` | `final class` | What a measured document, a length or a size cannot be |
| `Application\Exception\UnknownProfile` | `final class` | A profile name the configuration does not declare |
| `Infrastructure\Console\PreflightCommand` | `final class` | `phil:print-preflight:check` |
| `Infrastructure\PagedMedia\PdfLayout` | `final class` | Draws on the native PDF writer and records what it places |
| `Infrastructure\Media\MediaImages` | `final readonly class` | The images of the media library placed on a page |
| `Infrastructure\Fonts\CatalogueFontResolver` | `final readonly class` | The fonts of the catalogue of `phil/fonts-bundle` |
| `Infrastructure\DependencyInjection\PrintPreflightServices` | `final class` | The services of the configuration |
| `Infrastructure\DependencyInjection\FontCataloguePass` | `final readonly class` | Removes the resolver when `phil/fonts-bundle` is not enabled |
