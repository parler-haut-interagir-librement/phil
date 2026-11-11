# Reference

Every type of the library, with its role.

| Type | Kind | Role |
|---|---|---|
| `Phil\Ujo\Ujo` | `final readonly class` | The library in one object: `decode()`, `normalize()`, `encode()`, `toHtml()`, `toText()` |
| `Phil\Ujo\Document` | `final readonly class` | A document: time, blocks, version; `fromArray()`, `toArray()`, `walk()`, `find()` |
| `Phil\Ujo\Block` | `final readonly class` | A block: identifier, type, data, tunes; `fromArray()`, `toArray()`, `children()`, `with*()` |
| `Phil\Ujo\Normalizer` | `final readonly class` | Checks a document into canonical form, at a validation level |
| `Phil\Ujo\Validation` | enum | `Shape`, the default, or `Publication`: how much of a document is checked |
| `Phil\Ujo\Json\JsonDecoder` | `final readonly class` | Strict reading of UJO JSON |
| `Phil\Ujo\Json\JsonEncoder` | `final readonly class` | Writing UJO JSON in canonical form |
| `Phil\Ujo\Schema\Field` | interface | A value of a block's data, checked and canonical |
| `Phil\Ujo\Schema\Shape` | `final readonly class` | An object of properties, closed or open |
| `Phil\Ujo\Schema\Property` | `final readonly class` | A property: required, optional or with a default; nullable; filled in for publication |
| `Phil\Ujo\Contract\Sized` | interface | A field whose values have a size: characters, items, blocks, entries |
| `Phil\Ujo\Schema\Bounded` | `final readonly class` | A sized field within bounds for publication |
| `Phil\Ujo\Schema\Html`, `Plain`, `Integer`, `Boolean`, `Choice`, `Link`, `ListOf`, `Blocks`, `Lazy`, `Dictionary`, `TextOrShape` | `final readonly class` | The fields |
| `Phil\Ujo\Schema\Context` | `final class` | The state of one normalization: identifiers met, depth, validation level |
| `Phil\Ujo\Type\BlockType` | `final readonly class` | A type: name, aliases, shape, `fold`, `upgrade`, `check`, `publication` |
| `Phil\Ujo\Type\BlockTypeRegistry` | `final readonly class` | Types by name or alias; `default()`, `with()`, `without()` |
| `Phil\Ujo\Type\StandardTypes` | `final class` | The official Editor.js tools |
| `Phil\Ujo\Type\CommunityTypes` | `final class` | The community tools |
| `Phil\Ujo\Type\MediaTypes` | `final class` | The video and sound players |
| `Phil\Ujo\Type\LayoutTypes` | `final class` | The page and game types |
| `Phil\Ujo\Type\TableGrid` | `final class` | The columns the cells of a table take, spans counted |
| `Phil\Ujo\Type\StoredMarkup` | `final class` | The `rendered` property of the drawn types, and its limits |
| `Phil\Ujo\Type\EmbedServices` | `final readonly class` | The services an embed may frame, and their hosts |
| `Phil\Ujo\Type\TuneType` | `final readonly class` | A tune: name, aliases, value |
| `Phil\Ujo\Type\TuneRegistry` | `final readonly class` | Tunes by name or alias |
| `Phil\Ujo\Type\Tunes` | `final class` | The default tunes |
| `Phil\Ujo\Html\InlineSanitizer` | `final readonly class` | Inline HTML kept as a profile allows; text of a fragment; escaping |
| `Phil\Ujo\Html\InlineProfile` | `final readonly class` | An ordered list of inline tools: `rich()`, `plain()`, `mathMl()`, `raw()` |
| `Phil\Ujo\Html\InlineTool` | `final readonly class` | An element kept: patterns read, tag and class written, attributes |
| `Phil\Ujo\Html\AttributeValue` | enum | What an attribute may hold, and its cleaning |
| `Phil\Ujo\Html\SafeUrl` | `final class` | Whether an address may be written, and its scheme |
| `Phil\Ujo\Html\LanguageTag` | `final class` | Whether a language tag is well-formed in BCP 47 |
| `Phil\Ujo\Html\HtmlRenderer` | `final readonly class` | Renders a document with a renderer per type |
| `Phil\Ujo\Html\BlockRenderer` | interface | Renders blocks of some types |
| `Phil\Ujo\Html\HtmlFilter` | interface | A last pass over the rendered HTML, which keeps it safe |
| `Phil\Ujo\Html\Rendering` | `final readonly class` | One rendering: document, nested blocks, sanitizer, elements with tunes |
| `Phil\Ujo\Html\Renderer\TextRenderer`, `ListRenderer`, `MediaRenderer`, `LayoutRenderer` | `final readonly class` | The default renderers |
| `Phil\Ujo\Html\Renderer\CodeRenderer` | `final readonly class` | The renderer of code blocks, with an optional highlighter whose HTML it checks |
| `Phil\Ujo\Html\Renderer\StoredMarkupRenderer` | `final readonly class` | Writes a block's trusted stored drawing, or falls back to another renderer |
| `Phil\Ujo\Text\PlainText` | `final readonly class` | A document as plain text, a conversion per type |
| `Phil\Ujo\Exception\UjoException` | interface, extends `Throwable` | Every exception of the library |
| `Phil\Ujo\Exception\InvalidJson` | `final class`, `InvalidArgumentException` | A text that is not UJO JSON |
| `Phil\Ujo\Exception\InvalidDocument` | `final class`, `InvalidArgumentException` | A structure or data that breaks the model, with its path |
| `Phil\Ujo\Exception\UnknownBlockType` | `final class`, `InvalidArgumentException` | A type, tune or renderer not registered |
| `Phil\Ujo\Exception\InvalidConfiguration` | `final class`, `LogicException` | An inline profile not registered |

See [documents.md](documents.md), [block-types.md](block-types.md), [normalization.md](normalization.md),
[inline-html.md](inline-html.md), [html-rendering.md](html-rendering.md), [plain-text.md](plain-text.md) and
[exceptions.md](exceptions.md).
