# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\PrintPreflight\Preflight` | `final readonly class` | Runs the checks on a document against a profile and returns the report |
| `Phil\PrintPreflight\PrintProfile` | `final readonly class` | What a printer asks of a file, and the severity of each rule for it |
| `Phil\PrintPreflight\ColourWorkflow` | enum | CMYK or RGB |
| `Phil\PrintPreflight\Document` | `final readonly class` | The pages of a document and the fonts it prints with |
| `Phil\PrintPreflight\Page` | `final readonly class` | A trim size, a bleed, images and boxes |
| `Phil\PrintPreflight\PlacedImage` | `final readonly class` | A raster image placed on a page, its effective resolution, ink coverage and transparency |
| `Phil\PrintPreflight\Box` | `final readonly class` | Any other element placed on a page, its ink coverage, overprint, line width and transparency |
| `Phil\PrintPreflight\UsedFont` | `final readonly class` | A font, its embedding permissions from `phil/fonts`, whether it is embedded and subset |
| `Phil\PrintPreflight\ColourSpace` | enum | Gray, RGB, CMYK or spot; read from a PDF colour space name |
| `Phil\PrintPreflight\Length` | `final readonly class` | A length in points, from millimetres, inches or CSS pixels; described in millimetres or points |
| `Phil\PrintPreflight\Size` | `final readonly class` | A trim size |
| `Phil\PrintPreflight\Rectangle` | `final readonly class` | The frame of an element from the top left corner of the trim box; from PDF coordinates |
| `Phil\PrintPreflight\Edge` | enum | Top, right, bottom, left |
| `Phil\PrintPreflight\Report` | `final readonly class` | The findings, document first then page by page |
| `Phil\PrintPreflight\Finding` | `final readonly class` | One finding: severity, code, sentence, page, subject, parameters |
| `Phil\PrintPreflight\Severity` | enum | Error, warning, notice |
| `Phil\PrintPreflight\Rule` | enum | The codes of the built-in rules and their default severities |
| `Phil\PrintPreflight\Check\Check` | interface | One family of rules |
| `Phil\PrintPreflight\Check\PageCount` | `final readonly class` | The page count against the minimum, maximum and multiple |
| `Phil\PrintPreflight\Check\PageGeometry` | `final readonly class` | Trim sizes and bleeds |
| `Phil\PrintPreflight\Check\Fonts` | `final readonly class` | Embedding and embedding permissions of fonts |
| `Phil\PrintPreflight\Check\ImageResolution` | `final readonly class` | Effective resolution of images |
| `Phil\PrintPreflight\Check\Colours` | `final readonly class` | Colour spaces against the workflow |
| `Phil\PrintPreflight\Check\InkCoverage` | `final readonly class` | Total ink coverage of images and flat colours |
| `Phil\PrintPreflight\Check\Overprint` | `final readonly class` | Elements set to overprint |
| `Phil\PrintPreflight\Check\Transparency` | `final readonly class` | Elements drawn with transparency |
| `Phil\PrintPreflight\Check\Hairlines` | `final readonly class` | Lines thinner than the printer reproduces |
| `Phil\PrintPreflight\Check\Placement` | `final readonly class` | Elements against the trim box, the safety margin and the bleed |
| `Phil\PrintPreflight\Exception\PrintPreflightException` | interface | Every exception of the library |
| `Phil\PrintPreflight\Exception\InvalidDocument` | `final class` | A document description that cannot be checked |
| `Phil\PrintPreflight\Exception\InvalidProfile` | `final class` | A profile whose rules contradict each other |
