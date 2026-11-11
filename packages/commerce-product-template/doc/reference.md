# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\CommerceProductTemplate\Template` | `final readonly class` | A blank: axes, faces, pricing, exclusions, SKU pattern, supplier |
| `Phil\CommerceProductTemplate\Axis` | `final readonly class` | What products vary by, and its values in order |
| `Phil\CommerceProductTemplate\AxisValue` | `final readonly class` | One value: code, label, swatch, supplier name, photos, channels |
| `Phil\CommerceProductTemplate\Label` | `final readonly class` | A text by locale |
| `Phil\CommerceProductTemplate\Swatch` | `final readonly class` | A colour or the photo of a sample |
| `Phil\CommerceProductTemplate\Face` | `final readonly class` | A face and its print zones |
| `Phil\CommerceProductTemplate\PrintZone` | `final readonly class` | A zone in millimetres, its resolution and its photo box |
| `Phil\CommerceProductTemplate\PhotoBox` | `final readonly class` | The rectangle of a zone on the photos of its face, in pixels |
| `Phil\CommerceProductTemplate\Pricing` | `final readonly class` | A base price, prices per value of one axis, supplements |
| `Phil\CommerceProductTemplate\Exclusion` | `final readonly class` | Combinations a template does not make |
| `Phil\CommerceProductTemplate\Combination` | `final readonly class` | One value of each axis |
| `Phil\CommerceProductTemplate\SkuPattern` | `final readonly class` | Literals and placeholders, rendered per product |
| `Phil\CommerceProductTemplate\Design` | `final readonly class` | What is printed: code, name, languages, artworks, templates, allowed values |
| `Phil\CommerceProductTemplate\LanguageVersion` | `final readonly class` | A language's SKU code and locale |
| `Phil\CommerceProductTemplate\Artwork` | `final readonly class` | An image in a zone, for a language and a condition |
| `Phil\CommerceProductTemplate\VariantPlanner` | `final readonly class` | Plans the products of a design on a template |
| `Phil\CommerceProductTemplate\Variant` | `final readonly class` | One planned product |
| `Phil\CommerceProductTemplate\PlacedArtwork` | `final readonly class` | An artwork with the face and zone it prints in |
| `Phil\CommerceProductTemplate\TemplateArray` | `final class` | A template as a versioned array, and back |
| `Phil\CommerceProductTemplate\DesignArray` | `final class` | A design as a versioned array, and back |
| `Phil\CommerceProductTemplate\Exception\ProductTemplateException` | interface | Every exception of the library |
| `Phil\CommerceProductTemplate\Exception\InvalidDefinition` | `final class` | A definition refused |
| `Phil\CommerceProductTemplate\Exception\PlanRefused` | `final class` | A plan refused |

`Internal\Check` and `Internal\Reader` are internal: they may change in any version.
