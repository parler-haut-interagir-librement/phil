<!-- phil-dev source-sha256=3e96b90021979038b724ce9e7ea7aab939340db2b7d17e8b5bf72b7061880299 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `PhilPrintPreflightBundle` | `final class` | Le bundle : sa configuration, l'attribut des vérifications, la passe du résolveur |
| `Application\PrintProfiles` | `final readonly class` | Les profils d'imprimeur par nom, et le profil par défaut |
| `Application\Lengths` | `final class` | Longueurs et formats lus à partir de texte |
| `Application\Preflighter` | `final readonly class` | Le contrôle préalable par rapport à un profil, avec les vérifications de l'application |
| `Application\AsPreflightCheck` | attribut | Enregistre une vérification de l'application |
| `Application\MeasuredDocument` | `final readonly class` | Un document mesuré dans un navigateur, lu à partir de JSON |
| `Application\Port\FontResolver` | interface | Le fichier de police derrière une famille, une graisse et un style |
| `Application\Exception\InvalidMeasurement` | `final class` | Ce que ne peut pas être un document mesuré, une longueur ou un format |
| `Application\Exception\UnknownProfile` | `final class` | Un nom de profil que la configuration ne déclare pas |
| `Infrastructure\Console\PreflightCommand` | `final class` | `phil:print-preflight:check` |
| `Infrastructure\PagedMedia\PdfLayout` | `final class` | Dessine sur le moteur PDF natif et enregistre ce qu'il place |
| `Infrastructure\Media\MediaImages` | `final readonly class` | Les images de la médiathèque placées sur une page |
| `Infrastructure\Fonts\CatalogueFontResolver` | `final readonly class` | Les polices du catalogue de `phil/fonts-bundle` |
| `Infrastructure\DependencyInjection\PrintPreflightServices` | `final class` | Les services de la configuration |
| `Infrastructure\DependencyInjection\FontCataloguePass` | `final readonly class` | Retire le résolveur quand `phil/fonts-bundle` n'est pas activé |
