<!-- phil-dev source-sha256=59432d6da4fd6e3ef0614e5eff9e3bc0be7ebdb99607d4d51bd77a12e8012401 -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `PhilEpubBundle` | `final class` | Le bundle : sa configuration, l'attribut des sources, la passe des intégrations |
| `Application\Epubs` | `final readonly class` | Écrit, conserve et construit des livres |
| `Application\BookSource` | interface | Construit un livre de l'application à partir d'un identifiant |
| `Application\AsBookSource` | attribut | Enregistre une source sous un nom |
| `Application\BookSources` | `final readonly class` | Les sources, par nom |
| `Application\Port\EpubStorage` | interface | L'endroit où les livres sont conservés |
| `Application\Message\WriteEpub` | `final readonly class` | Écrit un livre dans un worker |
| `Application\Event\EpubStored` | `final readonly class` | Réparti une fois que le livre d'un message est conservé |
| `Application\Exception\UnknownBookSource` | `final class` | Un nom de source sous lequel aucun service n'est enregistré |
| `Application\Exception\StorageFailed` | `final class` | Un livre que le stockage n'a pas pu conserver |
| `Application\Exception\FontNotEmbeddable` | `final class` | Une police qu'un livre ne peut pas transporter |
| `Infrastructure\Storage\DirectoryStorage` | `final readonly class` | Conserve les livres dans un répertoire |
| `Infrastructure\Media\MediaLibraryStorage` | `final readonly class` | Conserve les livres dans la médiathèque |
| `Infrastructure\Media\MediaAssets` | `final readonly class` | Des médias comme ressources d'un livre |
| `Infrastructure\Fonts\EpubFonts` | `final readonly class` | Incorpore des familles du répertoire de polices |
| `Infrastructure\Messenger\EpubHandler` | `final readonly class` | Traite `WriteEpub` |
| `Infrastructure\Console\WriteEpubCommand` | `final class` | `phil:epub:write` |
| `Infrastructure\DependencyInjection\EpubServices` | `final class` | Les services de la configuration |
| `Infrastructure\DependencyInjection\RequiredServicesPass` | `final readonly class` | Retire une intégration dont le bundle n'est pas activé |
