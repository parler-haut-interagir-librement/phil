<!-- phil-dev source-sha256=616cab180aa91dd5e1420da847156103082d82ee28d27c110d82f67901be86f7 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `Url` | `final readonly class` | Une adresse Web absolue, normalisée |
| `UrlType` | interface, pour les enums | Un genre d'adresse et sa règle |
| `KnownUrlType` | enum adossée à une chaîne, `UrlType` | Les genres internationaux |
| `TypedUrl` | `final readonly class` | Une adresse et son genre, vérifiés |
| `UrlTypes` | `final class` | La détection du genre d'une adresse parmi des ensembles d'enums |
| `ServiceUrl` | `final readonly class` | L'adresse d'un service sur un hôte déclaré : un nom sans domaine ou une adresse IP ; `fromString()`, `resolve()`, `origin()` |
| `ServiceHosts` | `final readonly class` | Les hôtes des services qu'une application déclare, normalisés ; `declares()`, `all()` |
| `CookielessVideo` | `final readonly class` | Le lecteur sans témoins d'une vidéo YouTube ou Vimeo ; `fromUrl()` |
| `Exception\UrlException` | interface | Chaque exception de la bibliothèque |
| `Exception\InvalidUrl` | `final class`, `InvalidArgumentException` | Une adresse refusée, ou qui n'est pas de son genre |
