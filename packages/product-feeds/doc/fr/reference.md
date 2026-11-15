<!-- phil-dev source-sha256=d2caa39ac97ce6f1112e73c81f2bc3303a046669ee2303c07af379445b3c47e7 -->
# Référence

Chaque type public du paquet, avec son rôle. Les types marqués internes ne font pas partie de l'API publique.

| Type | Genre | Rôle |
|---|---|---|
| `FeedItem` | `final readonly class` | Un produit ou une variante, ses champs vérifiés à sa création ; `hasIdentifier()` |
| `Availability` | `enum` | `InStock`, `OutOfStock`, `Preorder`, `Backorder` ; `takesADate()` |
| `Condition` | `enum` | `New`, `Refurbished`, `Used` |
| `AgeGroup` | `enum` | `Newborn`, `Infant`, `Toddler`, `Kids`, `Adult` |
| `Gender` | `enum` | `Male`, `Female`, `Unisex` |
| `Shipping` | `final readonly class` | Ce que coûte la livraison d'un article vers un pays, par un service ou par n'importe lequel |
| `SalePeriod` | `final readonly class` | Quand un prix soldé s'applique, en UTC ; `toString()` comme intervalle ISO 8601 |
| `FeedChannel` | `final readonly class` | Le titre, le lien et la description du flux |
| `FeedFormat` | `enum` | `Xml`, `Tsv` ; `serializer()`, `extension()`, `mediaType()` |
| `FeedPublisher` | `final readonly class` | Écrit un fichier de flux d'un bloc, en mémoire constante, et le renomme en place ; `publish()` |
| `PublishedFeed` | `final readonly class` | Le chemin, les articles et les octets d'un flux écrit |
| `Contract\ProductSource` | interface | Les produits qu'un flux publie |
| `Contract\FeedSerializer` | interface | Écrit l'en-tête, les articles et le pied d'un flux |
| `Serializer\RssSerializer` | `final readonly class` | RSS 2.0 avec l'espace de noms `g:` des données de produit |
| `Serializer\TsvSerializer` | `final readonly class` | Valeurs séparées par des tabulations avec une ligne d'en-tête |
| `Serializer\Fields` | `final class`, interne | Les champs d'un article par nom, dans l'ordre où les deux formats les écrivent |
| `Text` | `final class`, interne | Le nettoyage et les longueurs des champs de texte |
| `Gtin` | `final class`, interne | La vérification des chiffres d'un GTIN |
| `Exception\ProductFeedException` | interface | Chaque exception du paquet |
| `Exception\InvalidFeedItem` | `final class`, `InvalidArgumentException` | Un champ qu'un article, un canal, une période ou des frais de livraison ne peuvent pas porter |
| `Exception\FeedNotWritten` | `final class`, `RuntimeException` | Un répertoire, un disque ou une source en échec, ou un flux trop long ; le fichier précédent reste en place |
