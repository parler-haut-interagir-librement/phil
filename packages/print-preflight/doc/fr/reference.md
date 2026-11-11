<!-- phil-dev source-sha256=b61faf79cfce514f9cf89684784c191608806a163b281911095e12c7d3ce762d -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `Phil\PrintPreflight\Preflight` | `final readonly class` | Exécute les vérifications sur un document selon un profil et renvoie le rapport |
| `Phil\PrintPreflight\PrintProfile` | `final readonly class` | Ce qu'un imprimeur exige d'un fichier, et la gravité de chaque règle pour lui |
| `Phil\PrintPreflight\ColourWorkflow` | enum | CMJN ou RVB |
| `Phil\PrintPreflight\Document` | `final readonly class` | Les pages d'un document et les polices avec lesquelles il s'imprime |
| `Phil\PrintPreflight\Page` | `final readonly class` | Un format rogné, un fond perdu, des images et des boîtes |
| `Phil\PrintPreflight\PlacedImage` | `final readonly class` | Une image matricielle placée sur une page, sa résolution effective, sa couverture d'encre et sa transparence |
| `Phil\PrintPreflight\Box` | `final readonly class` | Tout autre élément placé sur une page, sa couverture d'encre, sa surimpression, son épaisseur de filet et sa transparence |
| `Phil\PrintPreflight\UsedFont` | `final readonly class` | Une police, ses permissions d'intégration de `phil/fonts`, si elle est intégrée et en sous-ensemble |
| `Phil\PrintPreflight\ColourSpace` | enum | Gris, RVB, CMJN ou ton direct ; lu à partir d'un nom d'espace colorimétrique PDF |
| `Phil\PrintPreflight\Length` | `final readonly class` | Une longueur en points, à partir de millimètres, de pouces ou de pixels CSS ; décrite en millimètres ou en points |
| `Phil\PrintPreflight\Size` | `final readonly class` | Un format rogné |
| `Phil\PrintPreflight\Rectangle` | `final readonly class` | Le cadre d'un élément depuis le coin supérieur gauche du format rogné ; à partir de coordonnées PDF |
| `Phil\PrintPreflight\Edge` | enum | Haut, droite, bas, gauche |
| `Phil\PrintPreflight\Report` | `final readonly class` | Les constats, le document d'abord, puis page par page |
| `Phil\PrintPreflight\Finding` | `final readonly class` | Un constat : gravité, code, phrase, page, sujet, paramètres |
| `Phil\PrintPreflight\Severity` | enum | Erreur, avertissement, remarque |
| `Phil\PrintPreflight\Rule` | enum | Les codes des règles intégrées et leurs gravités par défaut |
| `Phil\PrintPreflight\Check\Check` | interface | Une famille de règles |
| `Phil\PrintPreflight\Check\PageCount` | `final readonly class` | Le nombre de pages par rapport au minimum, au maximum et au multiple |
| `Phil\PrintPreflight\Check\PageGeometry` | `final readonly class` | Formats rognés et fonds perdus |
| `Phil\PrintPreflight\Check\Fonts` | `final readonly class` | Intégration et permissions d'intégration des polices |
| `Phil\PrintPreflight\Check\ImageResolution` | `final readonly class` | Résolution effective des images |
| `Phil\PrintPreflight\Check\Colours` | `final readonly class` | Espaces colorimétriques par rapport au flux |
| `Phil\PrintPreflight\Check\InkCoverage` | `final readonly class` | Couverture d'encre totale des images et des aplats |
| `Phil\PrintPreflight\Check\Overprint` | `final readonly class` | Éléments en surimpression |
| `Phil\PrintPreflight\Check\Transparency` | `final readonly class` | Éléments dessinés avec de la transparence |
| `Phil\PrintPreflight\Check\Hairlines` | `final readonly class` | Filets plus fins que ce que l'imprimeur reproduit |
| `Phil\PrintPreflight\Check\Placement` | `final readonly class` | Éléments par rapport au format rogné, à la marge de sécurité et au fond perdu |
| `Phil\PrintPreflight\Exception\PrintPreflightException` | interface | Toute exception de la bibliothèque |
| `Phil\PrintPreflight\Exception\InvalidDocument` | `final class` | Une description de document qui ne peut pas être vérifiée |
| `Phil\PrintPreflight\Exception\InvalidProfile` | `final class` | Un profil dont les règles se contredisent |
