<!-- phil-dev source-sha256=719c49613535542016f1942960b4848ff96c59d30b5786340ea630df251ad3d0 -->
# Référence

| Type | Sorte | Rôle |
|---|---|---|
| `Phil\SocialCards\ShareMetadata` | `final readonly class` | Les métadonnées de partage d'une page |
| `Phil\SocialCards\ShareImage` | `final readonly class` | L'image d'une page partagée : adresse, texte de remplacement, taille, type de média |
| `Phil\SocialCards\ShareType` | enum | Les types Open Graph |
| `Phil\SocialCards\TwitterCard` | enum | `summary` et `summary_large_image` |
| `Phil\SocialCards\MetaTags` | `final class` | Les balises d'une page, et leur HTML |
| `Phil\SocialCards\MetaTag` | `final readonly class` | Une balise |
| `Phil\SocialCards\MetaAttribute` | enum | `property`, `name` ou un `<link rel>` |
| `Phil\SocialCards\Text` | `final class`, interne | Des textes sur une ligne, des adresses vérifiées et résolues |
| `Phil\SocialCards\Card\CardTemplate` | `final readonly class` | L'apparence des cartes |
| `Phil\SocialCards\Card\CardFont` | `final readonly class` | Une police d'un gabarit, lue par phil/fonts |
| `Phil\SocialCards\Card\CardImage` | `final readonly class` | Un fond ou un logo, borné |
| `Phil\SocialCards\Card\Color` | `final readonly class` | Une couleur en hexadécimal |
| `Phil\SocialCards\Card\CardContent` | `final readonly class` | Le titre, le sous-titre et la locale d'une carte |
| `Phil\SocialCards\Card\LineBreaker` | `final class` | Les endroits où une ligne peut se briser, par ICU |
| `Phil\SocialCards\Card\TextFitter` | `final readonly class` | La plus grande taille à laquelle un texte tient dans une boîte |
| `Phil\SocialCards\Card\FittedText` | `final readonly class` | Les lignes d'un texte ajusté et leur taille |
| `Phil\SocialCards\Card\GdCardRenderer` | `final readonly class` | Le dessin avec GD et FreeType |
| `Phil\SocialCards\Card\GdTextMeasure` | `final class` | La mesure du texte avec GD |
| `Phil\SocialCards\Card\CardGenerator` | `final readonly class` | Chaque carte dessinée une fois, sous son empreinte |
| `Phil\SocialCards\Card\GeneratedCard` | `final readonly class` | Une empreinte et son PNG |
| `Phil\SocialCards\Card\Fingerprint` | `final class` | La règle d'une empreinte |
| `Phil\SocialCards\Card\DirectoryCardStore` | `final readonly class` | Les cartes sous forme de fichiers |
| `Phil\SocialCards\Card\InMemoryCardStore` | `final class` | Les cartes en mémoire |
| `Phil\SocialCards\Contract\CardRenderer` | interface | Dessine une carte |
| `Phil\SocialCards\Contract\TextMeasure` | interface | Mesure une ligne |
| `Phil\SocialCards\Contract\CardStore` | interface | Garde les cartes |
| `Phil\SocialCards\Preview\TagReader` | `final readonly class` | Les balises d'une page d'un autre site |
| `Phil\SocialCards\Preview\PageTags` | `final readonly class` | Les balises lues |
| `Phil\SocialCards\Preview\LinkPreview` | `final readonly class` | La carte d'un lien |
| `Phil\SocialCards\Preview\TagCheck` | `final class` | Ce qui manque ou ce qui est faux dans les balises |
| `Phil\SocialCards\Preview\LinkFinder` | `final class` | Les liens d'un texte brut, le premier étant celui qu'un message prévisualise |
| `Phil\SocialCards\Preview\Finding` | `final readonly class` | Un constat |
| `Phil\SocialCards\Preview\Severity` | enum | Erreur ou avertissement |
| `Phil\SocialCards\Exception\SocialCardsException` | interface | Chaque exception de la bibliothèque |
| `Phil\SocialCards\Exception\InvalidShareMetadata` | `final class` | Des métadonnées de partage que les balises ne peuvent pas porter |
| `Phil\SocialCards\Exception\InvalidCardTemplate` | `final class` | Un gabarit ou une carte qui ne peut pas être dessiné |
| `Phil\SocialCards\Exception\CardRenderingFailed` | `final class` | GD ou un stockage a échoué |
