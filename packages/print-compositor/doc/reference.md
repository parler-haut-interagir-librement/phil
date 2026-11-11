# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\PrintCompositor\PrintCompositor` | `final readonly class` | Draws a job into one print file per ink |
| `Phil\PrintCompositor\PrintJob` | `final readonly class` | A zone, its layers in order and a resolution |
| `Phil\PrintCompositor\PrintFiles` | `final readonly class` | The print files of a job, by ink, as rasters or PNG |
| `Phil\PrintCompositor\Layer` | `final readonly class` | Items drawn in order in one ink |
| `Phil\PrintCompositor\Item` | interface | Something drawn in a layer, inside its box |
| `Phil\PrintCompositor\ImageItem` | `final readonly class` | An image scaled into its box |
| `Phil\PrintCompositor\TextItem` | `final readonly class` | A text in a font, a colour and a range of sizes, in its box |
| `Phil\PrintCompositor\TypeFace` | `final readonly class` | A TrueType or OpenType file and what `phil/fonts` reads of it |
| `Phil\PrintCompositor\TextFitter` | `final class` | Finds the size of a text in its box, or refuses it |
| `Phil\PrintCompositor\FittedText` | `final readonly class` | A text laid out: its size, its lines, the size of its ink |
| `Phil\PrintCompositor\FittedLine` | `final readonly class` | One line: its baseline, its left side bearing and its width |
| `Phil\PrintCompositor\MockupCompositor` | `final class` | Places artworks on a face photo with the fabric's shading |
| `Phil\PrintCompositor\Mockup` | `final readonly class` | A face photo, its placements and the shading strength |
| `Phil\PrintCompositor\FacePhoto` | `final readonly class` | The photo of one face in one variant, and the rectangle of each zone on it |
| `Phil\PrintCompositor\Placement` | `final readonly class` | An artwork in a zone of a photo, and its alignment |
| `Phil\PrintCompositor\Raster` | `final class` | An image in true colour with its alpha: decoding, pixels, PNG and WebP |
| `Phil\PrintCompositor\Colour` | `final readonly class` | An sRGB colour with its opacity |
| `Phil\PrintCompositor\Zone` | `final readonly class` | A print zone and its real size in millimetres |
| `Phil\PrintCompositor\Box` | `final readonly class` | A rectangle in millimetres in a zone |
| `Phil\PrintCompositor\PixelBox` | `final readonly class` | A rectangle in pixels in an image |
| `Phil\PrintCompositor\Units` | `final class` | Millimetres and points turned into pixels |
| `Phil\PrintCompositor\Fit` | enum | Contain or cover |
| `Phil\PrintCompositor\HorizontalAlign` | enum | Left, centre, right |
| `Phil\PrintCompositor\VerticalAlign` | enum | Top, middle, bottom |
| `Phil\PrintCompositor\Internal\Gd` | `final class`, internal | The GD calls of the library |
| `Phil\PrintCompositor\Exception\PrintCompositorException` | interface | Implemented by every exception of the library |
| `Phil\PrintCompositor\Exception\InvalidLayout` | `final class` | A layout that cannot be composed |
| `Phil\PrintCompositor\Exception\InvalidImage` | `final class` | An image that cannot be read or is too large |
| `Phil\PrintCompositor\Exception\UnsupportedFont` | `final class` | A font that cannot be read or drawn |
| `Phil\PrintCompositor\Exception\MissingGlyphs` | `final class` | A text with characters its font does not cover |
| `Phil\PrintCompositor\Exception\TextDoesNotFit` | `final class` | A text that does not fit its box |
| `Phil\PrintCompositor\Exception\MissingCapability` | `final class` | A GD build without what a call needs |
