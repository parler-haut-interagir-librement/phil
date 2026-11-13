<!-- phil-dev source-sha256=7381688d414a2dc628e2222a2349b1fdc9c0e9ac9b9a88c0703e9922e9be9476 -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `PhilFontsBundle` | `final class` | Le bundle : sa configuration et la passe des services facultatifs |
| `Application\FontFiles` | `final readonly class` | Lit les fichiers de police, WOFF2 quand `ext-brotli` est chargée |
| `Application\FontCatalogue` | `final class` | Les fichiers de police d'un répertoire, lus une fois ; les faces par famille, graisse et style |
| `Application\CatalogueEntry` | `final readonly class` | Un fichier du catalogue : chemin, format, fichier ou raison |
| `Application\FontFaces` | `final readonly class` | Les règles `@font-face` du catalogue |
| `Application\Licences` | `final readonly class` | Le registre des licences, comparé au catalogue |
| `Application\Exception\FontUnreadable` | `final class` | Un fichier de police ou un registre illisible |
| `Application\Exception\FontNotFound` | `final class` | Un fichier ou une face absente du catalogue |
| `Infrastructure\Console\ListFontsCommand` | `final class` | `phil:fonts:list` |
| `Infrastructure\Console\CheckLicencesCommand` | `final class` | `phil:fonts:licences` |
| `Infrastructure\Console\FontFacesCommand` | `final class` | `phil:fonts:css` |
| `Infrastructure\Twig\FontsExtension` | `final readonly class` | `fonts_css()` |
| `Infrastructure\PagedMedia\PdfFonts` | `final readonly class` | Les faces TrueType pour le générateur de PDF natif |
| `Infrastructure\Media\MediaFonts` | `final readonly class` | Les polices de la médiathèque |
| `Infrastructure\DependencyInjection\FontsServices` | `final class` | Les services de la configuration |
| `Infrastructure\DependencyInjection\RequiredServicesPass` | `final readonly class` | Retire un service dont le bundle n'est pas activé |
