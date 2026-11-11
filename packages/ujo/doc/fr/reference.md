<!-- phil-dev source-sha256=4ec862bd831b6cf281903e8c9330568f0804b2e092ab0a39ce331d1ea3ba87ba -->
# Référence

Chaque type de la bibliothèque, avec son rôle.

| Type | Nature | Rôle |
|---|---|---|
| `Phil\Ujo\Ujo` | `final readonly class` | La bibliothèque en un seul objet : `decode()`, `normalize()`, `encode()`, `toHtml()`, `toText()` |
| `Phil\Ujo\Document` | `final readonly class` | Un document : heure, blocs, version ; `fromArray()`, `toArray()`, `walk()`, `find()` |
| `Phil\Ujo\Block` | `final readonly class` | Un bloc : identifiant, type, données, réglages ; `fromArray()`, `toArray()`, `children()`, `with*()` |
| `Phil\Ujo\Normalizer` | `final readonly class` | Vérifie un document et le met sous forme canonique, à un niveau de validation |
| `Phil\Ujo\Validation` | enum | `Shape`, par défaut, ou `Publication` : ce que l'on vérifie d'un document |
| `Phil\Ujo\Json\JsonDecoder` | `final readonly class` | La lecture stricte du JSON UJO |
| `Phil\Ujo\Json\JsonEncoder` | `final readonly class` | L'écriture du JSON UJO sous forme canonique |
| `Phil\Ujo\Schema\Field` | interface | Une valeur des données d'un bloc, vérifiée et canonique |
| `Phil\Ujo\Schema\Shape` | `final readonly class` | Un objet de propriétés, fermé ou ouvert |
| `Phil\Ujo\Schema\Property` | `final readonly class` | Une propriété : obligatoire, facultative ou avec une valeur par défaut ; nullable ; remplie pour la publication |
| `Phil\Ujo\Contract\Sized` | interface | Un champ dont les valeurs ont une taille : caractères, éléments, blocs, entrées |
| `Phil\Ujo\Schema\Bounded` | `final readonly class` | Un champ mesurable dans des bornes pour la publication |
| `Phil\Ujo\Schema\Html`, `Plain`, `Integer`, `Boolean`, `Choice`, `Link`, `ListOf`, `Blocks`, `Lazy`, `Dictionary`, `TextOrShape` | `final readonly class` | Les champs |
| `Phil\Ujo\Schema\Context` | `final class` | L'état d'une normalisation : identifiants rencontrés, profondeur, niveau de validation |
| `Phil\Ujo\Type\BlockType` | `final readonly class` | Un type : nom, alias, forme, `fold`, `upgrade`, `check`, `publication` |
| `Phil\Ujo\Type\BlockTypeRegistry` | `final readonly class` | Les types par nom ou alias ; `default()`, `with()`, `without()` |
| `Phil\Ujo\Type\StandardTypes` | `final class` | Les outils officiels d'Editor.js |
| `Phil\Ujo\Type\CommunityTypes` | `final class` | Les outils communautaires |
| `Phil\Ujo\Type\MediaTypes` | `final class` | Les lecteurs vidéo et audio |
| `Phil\Ujo\Type\LayoutTypes` | `final class` | Les types de page et de jeu |
| `Phil\Ujo\Type\TableGrid` | `final class` | Les colonnes qu'occupent les cellules d'un tableau, fusions comprises |
| `Phil\Ujo\Type\StoredMarkup` | `final class` | La propriété `rendered` des types dessinés, et ses limites |
| `Phil\Ujo\Type\EmbedServices` | `final readonly class` | Les services qu'une intégration peut encadrer, et leurs hôtes |
| `Phil\Ujo\Type\TuneType` | `final readonly class` | Un réglage : nom, alias, valeur |
| `Phil\Ujo\Type\TuneRegistry` | `final readonly class` | Les réglages par nom ou alias |
| `Phil\Ujo\Type\Tunes` | `final class` | Les réglages par défaut |
| `Phil\Ujo\Html\InlineSanitizer` | `final readonly class` | Le HTML en ligne gardé comme un profil le permet ; le texte d'un fragment ; l'échappement |
| `Phil\Ujo\Html\InlineProfile` | `final readonly class` | Une liste ordonnée d'outils en ligne : `rich()`, `plain()`, `mathMl()`, `raw()` |
| `Phil\Ujo\Html\InlineTool` | `final readonly class` | Un élément gardé : motifs lus, balise et classe écrites, attributs |
| `Phil\Ujo\Html\AttributeValue` | enum | Ce qu'un attribut peut contenir, et son nettoyage |
| `Phil\Ujo\Html\SafeUrl` | `final class` | Si une adresse peut être écrite, et son schéma |
| `Phil\Ujo\Html\LanguageTag` | `final class` | Si une étiquette de langue est bien formée selon BCP 47 |
| `Phil\Ujo\Html\HtmlRenderer` | `final readonly class` | Produit le rendu d'un document avec un moteur de rendu par type |
| `Phil\Ujo\Html\BlockRenderer` | interface | Produit le rendu des blocs de certains types |
| `Phil\Ujo\Html\HtmlFilter` | interface | Une dernière passe sur le HTML produit, qui le garde sûr |
| `Phil\Ujo\Html\Rendering` | `final readonly class` | Un rendu : document, blocs imbriqués, assainisseur, éléments avec réglages |
| `Phil\Ujo\Html\Renderer\TextRenderer`, `ListRenderer`, `MediaRenderer`, `LayoutRenderer` | `final readonly class` | Les moteurs de rendu par défaut |
| `Phil\Ujo\Html\Renderer\CodeRenderer` | `final readonly class` | Le moteur de rendu des blocs de code, avec un surligneur facultatif dont il vérifie le HTML |
| `Phil\Ujo\Html\Renderer\StoredMarkupRenderer` | `final readonly class` | Écrit le dessin stocké et approuvé d'un bloc, ou se rabat sur un autre moteur de rendu |
| `Phil\Ujo\Text\PlainText` | `final readonly class` | Un document en texte brut, une conversion par type |
| `Phil\Ujo\Exception\UjoException` | interface, étend `Throwable` | Chaque exception de la bibliothèque |
| `Phil\Ujo\Exception\InvalidJson` | `final class`, `InvalidArgumentException` | Un texte qui n'est pas du JSON UJO |
| `Phil\Ujo\Exception\InvalidDocument` | `final class`, `InvalidArgumentException` | Une structure ou des données qui enfreignent le modèle, avec leur chemin |
| `Phil\Ujo\Exception\UnknownBlockType` | `final class`, `InvalidArgumentException` | Un type, un réglage ou un moteur de rendu non enregistré |
| `Phil\Ujo\Exception\InvalidConfiguration` | `final class`, `LogicException` | Un profil en ligne non enregistré |

Voir [documents.md](documents.md), [block-types.md](block-types.md), [normalization.md](normalization.md),
[inline-html.md](inline-html.md), [html-rendering.md](html-rendering.md), [plain-text.md](plain-text.md) et
[exceptions.md](exceptions.md).
