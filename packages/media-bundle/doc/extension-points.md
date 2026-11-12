# Extension points

What an application implements, replaces, configures or overrides. Each port is an alias that a service of the
application's own, aliased to the interface, replaces.

## Ports

### `MediaStorage`

`Phil\MediaBundle\Application\MediaStorage` — where files are kept, by key: `originals/{id}/{version}/{name}`,
`formats/{id}/…`. `FilesystemStorage` over `storage_dir`, or `FlysystemStorage` over the `filesystem` service. See
[Storage](storage.md).

| Method | Contract |
|---|---|
| `write(string $key, string $contents): void` | Keeps the file under the key, replacing any |
| `read(string $key): ?string` | Its contents, or `null` when there is none |
| `deletePrefix(string $prefix): void` | Deletes every file whose key starts so |

### `ImageProcessor`

`Phil\MediaBundle\Application\ImageProcessor` — draws a format. `GdImageProcessor` draws with GD and hands WebP and AVIF
to `cwebp` and `avifenc` when they are installed. See [Formats and URLs](formats.md#rendering).

| Method | Contract |
|---|---|
| `render(string $image, ImageFormat $format, array $focalPoint, string $type): string` | The image's bytes drawn to the format around the focal point — from 0 to 1 across and down —, in `webp`, `avif`, `jpeg` or `png` |

A processor of the application's own bounds what it decodes, as `GdImageProcessor` refuses an image larger than
`max_pixels` before decoding it: the files come from uploads. `VipsImageProcessor` is the other adapter, chosen by
`processor`.

### `SvgPurifier`

`Phil\MediaBundle\Application\Contract\SvgPurifier` — purifies an SVG image before it is kept.
`SanitizingSvgPurifier`, wired when `phil/svg-sanitizer` is installed, sanitizes then minifies.

| Method | Contract |
|---|---|
| `purify(string $svg): string` | The image to keep; `InvalidMedia` when it is refused whole |

### `MediaReferences`

`Phil\MediaBundle\Application\MediaReferences` — which records show a medium, asked before a deletion.
`DoctrineMediaReferences` reads every `phil_media` column of the mapped entities; `NoMediaReferences` stands without
Doctrine. See [Records that show media](doctrine.md#what-a-deletion-does).

| Method | Contract |
|---|---|
| `of(MediaId $id): list<MediaReference>` | The columns that hold the medium, each with its number of records and its behaviour |
| `detach(MediaId $id, MediaReference $reference): void` | Empties that column in every record that holds the medium |

### `MediaRepository`

`Phil\MediaBundle\Domain\Repository\MediaRepository` — media, versions and collections, a public alias of
`DoctrineMediaRepository`. An application's own repository implements every method, `getMany()` included.

| Method | Contract |
|---|---|
| `getMany(string ...$ids): array<string, Media>` | The media found, by identifier, in the order asked; each read once, the unknown left out |

### `Transformer`

`Phil\MediaBundle\Application\Transform\Transformer` — one transformation of a format, as a `Step`: a part of the
image copied, scaled, into a part of a new canvas. The configuration names the shipped types — `thumbnail`, `crop`,
`resize`, `widen`, `heighten`, `expand`; in PHP, `ImageFormat::transforming()` takes any `Transformer`.

| Method | Contract |
|---|---|
| `step(Geometry $image): Step` | The step that draws the image as the previous transformation left it |
| `definition(): array` | What it is, as configured: `['type' => 'crop', 'width' => 300, …]` |

## Configuration

| Option | What it extends |
|---|---|
| `formats`, `original` | The variations of images, their transformations, quality and widths |
| `storage_dir`, `filesystem` | Where files are kept |
| `secret`, `url_ttl` | Signed URLs |
| `max_pixels` | The largest image decoded |
| `encoders` | The WebP and AVIF encoders |
| `processor`, `vips_binary` | GD or libvips |
| `placeholder` | The tiny previews: on or off, their box and quality |
| `svg` | SVG purification, on or off, and the precision of coordinates |
| `delete_behaviour` | What a deletion does to the records that show a medium, per column |
| `admin` | The file explorer's layout, page size, largest upload and access attribute |

See [Installation](installation.md).

## Templates, forms and components

| Point | How |
|---|---|
| Responsive images | The `Img` and `Picture` components, their `priority`, their placeholder and its `placeholder` Stimulus controller — see [Responsive images](images.md) |
| User content | The policy of `phil/browser-security-bundle`, configured there — see [Formats and URLs](formats.md#under-the-policy-of-user-content) |
| The file explorer | Its `layout`, a template of the application's with a `content` block; the bundle's templates overridden in `templates/bundles/PhilMediaBundle/` — see [The file explorer](admin.md) |
| The picker field | `MediaPickerType` and its `collection`; its theme `@PhilMedia/form/media_picker.html.twig`; what may be chosen limited by `MediaFile` |
| EasyAdmin | The media field and its configurator, the menu link `MediaAdmin::menuItem()` — see [EasyAdmin](easyadmin.md) |
