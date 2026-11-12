# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilMediaBundle` | `final class`, `AbstractBundle` | Alias `phil_media` |
| `Domain\Model\Media`, `MediaVersion`, `MediaCollection` | entities | A medium; a previous file; a collection |
| `Domain\ValueObject\FileInfo` | value | A file's name, media type, size and dimensions |
| `Domain\ValueObject\MediaId` | value | A medium's identifier, as a `phil_media` column holds it |
| `Domain\Media\AiDisclosure` | enum | `None`, `Assisted`, `Generated` |
| `Domain\Repository\MediaRepository` | interface | Saving, finding, listing |
| `Domain\Exception\MediaException`, `InvalidMedia`, `MediaInUse` | exceptions | Everything the bundle throws |
| `Application\MediaLibrary` | `final readonly class` | Upload, replace, delete, read, render |
| `Application\ImageFormat`, `RenderBox` | values | A format: its transformations, quality and narrower widths; the part of an image a thumbnail keeps and the size rendered |
| `Application\Transform\Transformer` | interface | One transformation: the `Step` it draws on an image's `Geometry`, and its definition |
| `Application\Transform\Thumbnail`, `Crop`, `Resize`, `Widen`, `Heighten`, `Expand` | values | The transformations |
| `Application\Transform\Transformers` | `final class` | Transformations read from their configuration; `TYPES` |
| `Application\Transform\Geometry`, `Step` | values | An image's size and focal point; a part of it drawn on a new canvas |
| `Application\UrlSigner` | `final readonly class` | Signs and verifies URLs |
| `Application\MediaReferences`, `MediaReference`, `DeleteBehaviour` | interface, value, enum | The records that show a medium, column by column, and what a deletion does to them |
| `Application\MediaSchema` | `final class` | The medium as schema.org data |
| `Application\MediaStorage`, `ImageProcessor` | interfaces | Where files are kept; how images are rendered |
| `Application\Contract\SvgPurifier` | interface | How an SVG image is purified before it is kept |
| `Infrastructure\Storage\FilesystemStorage` | adapter | A directory |
| `Infrastructure\Storage\FlysystemStorage` | adapter | A Flysystem filesystem: local, S3, SFTP |
| `Infrastructure\Image\GdImageProcessor` | adapter | GD, with a pixel limit and optional binary encoders |
| `Infrastructure\Image\BinaryEncoder` | `final readonly class` | `cwebp` or `avifenc`, found on the `PATH` or named |
| `Infrastructure\Image\VipsImageProcessor` | adapter | libvips through `vips` and `vipsheader`, found on the `PATH` or named |
| `Infrastructure\Image\ImageProcessors` | `final class` | The processor the configuration chooses: GD, libvips, or libvips when found |
| `Infrastructure\Svg\SanitizingSvgPurifier` | adapter | `phil/svg-sanitizer`, then `SvgMinifier` |
| `Infrastructure\Svg\SvgMinifier` | `final readonly class` | Comments, `metadata` and whitespace dropped, coordinates rounded |
| `Infrastructure\Doctrine\DoctrineMediaRepository` | adapter | Doctrine ORM |
| `Infrastructure\Doctrine\MediaType` | DBAL type | The column type `phil_media` |
| `Infrastructure\Doctrine\DoctrineMediaReferences` | adapter | The `phil_media` columns of the mapped entities |
| `Infrastructure\Validator\MediaFile`, `MediaFileValidator` | constraint | Existence, collections and media types |
| `Infrastructure\Twig\Component\Img`, `Picture` | Twig components | `PhilMedia:Img`, `PhilMedia:Picture` |
| `Infrastructure\Twig\ResponsiveImages`, `ImageCandidates` | service, value | Each file type's URL, `srcset` and size; the placeholder and its position; the preload of a priority image |
| `Infrastructure\Console\PlaceholdersCommand` | console command | `phil:media:placeholders`: a tiny preview for the images kept without one |
| `Infrastructure\Admin\MediaAdminController` | controller | The file explorer and its picker; `INDEX_ROUTE`, `VIEWS`, `OUTCOMES` |
| `Infrastructure\Admin\ExplorerAccess` | service | Who may use the explorer, by the configured attribute, and whether a form comes from it, by its origin and the stateless CSRF token `phil_media_explorer` |
| `Infrastructure\DependencyInjection\CsrfProtectionPass` | compiler pass | Stops the container with a message that says what to change when `framework.csrf_protection` is false |
| `Infrastructure\Form\MediaPickerType` | form type | The field that opens the picker |
| `Infrastructure\EasyAdmin\MediaField`, `MediaFieldConfigurator` | EasyAdmin field | A medium in a CRUD controller |
| `Infrastructure\EasyAdmin\MediaAdmin` | `final class` | The link of an EasyAdmin menu to the file explorer |
| `Infrastructure\Profiler\MediaDataCollector`, `MediaTrace`, `MediaReport`, `TraceableMediaStorage`, `TraceableImageProcessor` | profiler | The media panel, in debug |
| `Infrastructure\Http\MediaController` | controller | The signed URLs; `INLINE_TYPES`, the types a browser is let show without the policy of user content; purified SVG images as `image/svg+xml` |
| `Infrastructure\Twig\MediaExtension` | Twig functions | `media_url()` — the file, a format, a narrower width —, `media_schema()` |
