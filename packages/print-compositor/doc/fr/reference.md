<!-- phil-dev source-sha256=2ae8770fdbcea1270845fcee16ab801246ac399459869a6692d8c1ffa3b65bfa -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `Phil\PrintCompositor\PrintCompositor` | `final readonly class` | Dessine une tâche en un fichier d'impression par encre |
| `Phil\PrintCompositor\PrintJob` | `final readonly class` | Une zone, ses calques dans l'ordre et une résolution |
| `Phil\PrintCompositor\PrintFiles` | `final readonly class` | Les fichiers d'impression d'une tâche, par encre, sous forme de matrices ou de PNG |
| `Phil\PrintCompositor\Layer` | `final readonly class` | Des éléments dessinés dans l'ordre dans une encre |
| `Phil\PrintCompositor\Item` | interface | Quelque chose de dessiné dans un calque, à l'intérieur de sa boîte |
| `Phil\PrintCompositor\ImageItem` | `final readonly class` | Une image mise à l'échelle dans sa boîte |
| `Phil\PrintCompositor\TextItem` | `final readonly class` | Un texte dans une police, une couleur et une plage de tailles, dans sa boîte |
| `Phil\PrintCompositor\TypeFace` | `final readonly class` | Un fichier TrueType ou OpenType et ce qu'en lit `phil/fonts` |
| `Phil\PrintCompositor\TextFitter` | `final class` | Trouve la taille d'un texte dans sa boîte, ou le refuse |
| `Phil\PrintCompositor\FittedText` | `final readonly class` | Un texte mis en page : sa taille, ses lignes, la taille de son encre |
| `Phil\PrintCompositor\FittedLine` | `final readonly class` | Une ligne : sa ligne de base, son approche gauche et sa largeur |
| `Phil\PrintCompositor\MockupCompositor` | `final class` | Place des visuels sur une photo de face avec l'ombrage du tissu |
| `Phil\PrintCompositor\Mockup` | `final readonly class` | Une photo de face, ses placements et l'intensité de l'ombrage |
| `Phil\PrintCompositor\FacePhoto` | `final readonly class` | La photo d'une face dans une variante, et le rectangle de chaque zone sur elle |
| `Phil\PrintCompositor\Placement` | `final readonly class` | Un visuel dans une zone d'une photo, et son alignement |
| `Phil\PrintCompositor\Raster` | `final class` | Une image en couleurs vraies avec son alpha : décodage, pixels, PNG et WebP |
| `Phil\PrintCompositor\Colour` | `final readonly class` | Une couleur sRGB avec son opacité |
| `Phil\PrintCompositor\Zone` | `final readonly class` | Une zone d'impression et sa taille réelle en millimètres |
| `Phil\PrintCompositor\Box` | `final readonly class` | Un rectangle en millimètres dans une zone |
| `Phil\PrintCompositor\PixelBox` | `final readonly class` | Un rectangle en pixels dans une image |
| `Phil\PrintCompositor\Units` | `final class` | Millimètres et points convertis en pixels |
| `Phil\PrintCompositor\Fit` | enum | Contenir ou couvrir |
| `Phil\PrintCompositor\HorizontalAlign` | enum | Gauche, centre, droite |
| `Phil\PrintCompositor\VerticalAlign` | enum | Haut, milieu, bas |
| `Phil\PrintCompositor\Internal\Gd` | `final class`, interne | Les appels à GD de la bibliothèque |
| `Phil\PrintCompositor\Exception\PrintCompositorException` | interface | Implémentée par chaque exception de la bibliothèque |
| `Phil\PrintCompositor\Exception\InvalidLayout` | `final class` | Une mise en page impossible à composer |
| `Phil\PrintCompositor\Exception\InvalidImage` | `final class` | Une image illisible ou trop grande |
| `Phil\PrintCompositor\Exception\UnsupportedFont` | `final class` | Une police impossible à lire ou à dessiner |
| `Phil\PrintCompositor\Exception\MissingGlyphs` | `final class` | Un texte dont des caractères ne sont pas couverts par sa police |
| `Phil\PrintCompositor\Exception\TextDoesNotFit` | `final class` | Un texte qui n'entre pas dans sa boîte |
| `Phil\PrintCompositor\Exception\MissingCapability` | `final class` | Une construction de GD sans ce dont un appel a besoin |
