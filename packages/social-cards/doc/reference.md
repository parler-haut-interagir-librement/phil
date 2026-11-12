# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\SocialCards\ShareMetadata` | `final readonly class` | The share metadata of a page |
| `Phil\SocialCards\ShareImage` | `final readonly class` | The image of a shared page: address, replacement text, size, media type |
| `Phil\SocialCards\ShareType` | enum | The Open Graph types |
| `Phil\SocialCards\TwitterCard` | enum | `summary` and `summary_large_image` |
| `Phil\SocialCards\MetaTags` | `final class` | The tags of a page, and their HTML |
| `Phil\SocialCards\MetaTag` | `final readonly class` | One tag |
| `Phil\SocialCards\MetaAttribute` | enum | `property`, `name` or a `<link rel>` |
| `Phil\SocialCards\Text` | `final class`, internal | Texts on one line, addresses checked and resolved |
| `Phil\SocialCards\Card\CardTemplate` | `final readonly class` | The look of the cards |
| `Phil\SocialCards\Card\CardFont` | `final readonly class` | A font of a template, read by phil/fonts |
| `Phil\SocialCards\Card\CardImage` | `final readonly class` | A background or a logo, bounded |
| `Phil\SocialCards\Card\Color` | `final readonly class` | A colour in hexadecimal |
| `Phil\SocialCards\Card\CardContent` | `final readonly class` | The title, subtitle and locale of a card |
| `Phil\SocialCards\Card\LineBreaker` | `final class` | Where a line may break, through ICU |
| `Phil\SocialCards\Card\TextFitter` | `final readonly class` | The largest size at which a text holds in a box |
| `Phil\SocialCards\Card\FittedText` | `final readonly class` | The lines of a fitted text and their size |
| `Phil\SocialCards\Card\GdCardRenderer` | `final readonly class` | The drawing with GD and FreeType |
| `Phil\SocialCards\Card\GdTextMeasure` | `final class` | The measure of text with GD |
| `Phil\SocialCards\Card\CardGenerator` | `final readonly class` | Each card drawn once, under its fingerprint |
| `Phil\SocialCards\Card\GeneratedCard` | `final readonly class` | A fingerprint and its PNG |
| `Phil\SocialCards\Card\Fingerprint` | `final class` | The rule of a fingerprint |
| `Phil\SocialCards\Card\DirectoryCardStore` | `final readonly class` | Cards as files |
| `Phil\SocialCards\Card\InMemoryCardStore` | `final class` | Cards in memory |
| `Phil\SocialCards\Contract\CardRenderer` | interface | Draws a card |
| `Phil\SocialCards\Contract\TextMeasure` | interface | Measures a line |
| `Phil\SocialCards\Contract\CardStore` | interface | Keeps the cards |
| `Phil\SocialCards\Preview\TagReader` | `final readonly class` | The tags of a page of another site |
| `Phil\SocialCards\Preview\PageTags` | `final readonly class` | The tags read |
| `Phil\SocialCards\Preview\LinkPreview` | `final readonly class` | The card of a link |
| `Phil\SocialCards\Preview\TagCheck` | `final class` | What is missing or wrong in the tags |
| `Phil\SocialCards\Preview\LinkFinder` | `final class` | The links of a plain text, the first one being the one a message previews |
| `Phil\SocialCards\Preview\Finding` | `final readonly class` | One finding |
| `Phil\SocialCards\Preview\Severity` | enum | Error or warning |
| `Phil\SocialCards\Exception\SocialCardsException` | interface | Every exception of the library |
| `Phil\SocialCards\Exception\InvalidShareMetadata` | `final class` | Share metadata that the tags cannot carry |
| `Phil\SocialCards\Exception\InvalidCardTemplate` | `final class` | A template or card that cannot be drawn |
| `Phil\SocialCards\Exception\CardRenderingFailed` | `final class` | GD or a store failed |
