# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilEpubBundle` | `final class` | The bundle: its configuration, the attribute of the sources, the pass of the integrations |
| `Application\Epubs` | `final readonly class` | Writes, keeps and builds books |
| `Application\BookSource` | interface | Builds a book of the application from an identifier |
| `Application\AsBookSource` | attribute | Registers a source under a name |
| `Application\BookSources` | `final readonly class` | The sources, by name |
| `Application\Port\EpubStorage` | interface | Where books are kept |
| `Application\Message\WriteEpub` | `final readonly class` | Writes a book in a worker |
| `Application\Event\EpubStored` | `final readonly class` | Dispatched once a message's book is kept |
| `Application\Exception\UnknownBookSource` | `final class` | A source name no service is registered under |
| `Application\Exception\StorageFailed` | `final class` | A book the storage could not keep |
| `Application\Exception\FontNotEmbeddable` | `final class` | A font a book cannot carry |
| `Infrastructure\Storage\DirectoryStorage` | `final readonly class` | Keeps books in a directory |
| `Infrastructure\Media\MediaLibraryStorage` | `final readonly class` | Keeps books in the media library |
| `Infrastructure\Media\MediaAssets` | `final readonly class` | Media as assets of a book |
| `Infrastructure\Fonts\EpubFonts` | `final readonly class` | Embeds families of the font directory |
| `Infrastructure\Messenger\EpubHandler` | `final readonly class` | Handles `WriteEpub` |
| `Infrastructure\Console\WriteEpubCommand` | `final class` | `phil:epub:write` |
| `Infrastructure\DependencyInjection\EpubServices` | `final class` | The services of the configuration |
| `Infrastructure\DependencyInjection\RequiredServicesPass` | `final readonly class` | Removes an integration whose bundle is not enabled |
