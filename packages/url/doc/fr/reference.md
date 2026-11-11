<!-- phil-dev source-sha256=8944fb42a5e327c3451658c5f0308538a8e19800375fb89982df0ee8d7d58984 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `Url` | `final readonly class` | Une adresse Web absolue, normalisée |
| `UrlType` | interface, pour les enums | Un genre d'adresse et sa règle |
| `KnownUrlType` | enum adossée à une chaîne, `UrlType` | Les genres internationaux |
| `TypedUrl` | `final readonly class` | Une adresse et son genre, vérifiés |
| `UrlTypes` | `final class` | La détection du genre d'une adresse parmi des ensembles d'enums |
| `CookielessVideo` | `final readonly class` | Le lecteur sans témoins d'une vidéo YouTube ou Vimeo ; `fromUrl()` |
| `Exception\UrlException` | interface | Chaque exception de la bibliothèque |
| `Exception\InvalidUrl` | `final class`, `InvalidArgumentException` | Une adresse refusée, ou qui n'est pas de son genre |
