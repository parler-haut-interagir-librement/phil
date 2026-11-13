# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilFontsBundle` | `final class` | The bundle: its configuration, and the pass of the optional services |
| `Application\FontFiles` | `final readonly class` | Reads font files, WOFF2 when `ext-brotli` is loaded |
| `Application\FontCatalogue` | `final class` | The font files of a directory, read once; faces by family, weight and style |
| `Application\CatalogueEntry` | `final readonly class` | One file of the catalogue: path, format, file or reason |
| `Application\FontFaces` | `final readonly class` | The `@font-face` rules of the catalogue |
| `Application\Licences` | `final readonly class` | The licence register, compared with the catalogue |
| `Application\Exception\FontUnreadable` | `final class` | A font file or register that cannot be read |
| `Application\Exception\FontNotFound` | `final class` | A file or face the catalogue does not hold |
| `Infrastructure\Console\ListFontsCommand` | `final class` | `phil:fonts:list` |
| `Infrastructure\Console\CheckLicencesCommand` | `final class` | `phil:fonts:licences` |
| `Infrastructure\Console\FontFacesCommand` | `final class` | `phil:fonts:css` |
| `Infrastructure\Twig\FontsExtension` | `final readonly class` | `fonts_css()` |
| `Infrastructure\PagedMedia\PdfFonts` | `final readonly class` | The TrueType faces for the native PDF writer |
| `Infrastructure\Media\MediaFonts` | `final readonly class` | The fonts of the media library |
| `Infrastructure\DependencyInjection\FontsServices` | `final class` | The services of the configuration |
| `Infrastructure\DependencyInjection\RequiredServicesPass` | `final readonly class` | Removes a service whose bundle is not enabled |
