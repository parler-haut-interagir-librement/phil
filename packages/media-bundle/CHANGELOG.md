# Changelog

All notable changes to `phil/media-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `Media`, `MediaVersion`, `MediaCollection`, `FileInfo`, `MediaId`, `AiDisclosure`, `MediaException`, `InvalidMedia`,
  which carries the failure below it, and `MediaInUse`; `Media::rename()` names the current file anew.
- `Media::disclose()` and `Media::digitalSourceType()`: how a medium was made, as an IPTC digital source type of
  `phil/creation-disclosure`, from which its AI disclosure follows; `Media::preview()` and `Media::placeholder()`, the
  tiny preview of the current version, emptied by a new version; the columns `digital_source_type` and `placeholder`.
- `MediaLibrary`, `ImageFormat`, `RenderBox`, `UrlSigner`, `MediaStorage`, `ImageProcessor` and `MediaRepository`, with
  `collections()` and `getMany()`, several media in one query, 500 identifiers at most per query; `MediaLibrary` takes a
  required `ClockInterface`, `MediaLibrary::replace()` deletes the previous version's renderings,
  `MediaLibrary::focus()` saves a new focal point and deletes the renderings around the previous one, and
  `MediaLibrary::rendered()` renders a format, one of its narrower widths, or the file itself converted, on first
  request; `MediaLibrary::placeholder()` computes the tiny WebP preview of each version of a raster image once, at
  upload and replacement, when a placeholder format is given, and `MediaLibrary::hasPlaceholders()` says whether it is;
  an SVG image is purified through `SvgPurifier` before it is kept, when one is given, and
  `MediaLibrary::purifiedSvg()` purifies it again the first time it is served, and keeps the result beside the
  formats of its version.
- The transformations `Thumbnail`, `Crop`, `Resize`, `Widen`, `Heighten` and `Expand` behind `Transformer`, read from
  their configuration by `Transformers`, each carrying the focal point through `Geometry` and `Step`;
  `ImageFormat::transforming()`, `ImageFormat::original()`, `atWidth()` and `apply()`.
- `MediaReferences`, `MediaReference` and `DeleteBehaviour`: `MediaLibrary::delete()` refuses with `MediaInUse`,
  empties, or leaves the records that show the medium, and `MediaLibrary::usages()` lists them.
- `MediaSchema`: the medium as a schema.org `ImageObject` or `MediaObject`, its declared digital source type, or its
  AI disclosure as the nearest one.
- `FilesystemStorage`; `FlysystemStorage`, on any Flysystem filesystem; `GdImageProcessor`, which refuses an image
  larger than `max_pixels` before decoding it and draws every rendering from the original; `BinaryEncoder`, which
  hands WebP and AVIF to `cwebp` and `avifenc`; `VipsImageProcessor`, which renders with libvips through `vips` and
  `vipsheader`, along the same steps, with only raster images within the pixel limit handed to it, and
  `ImageProcessors`, which chooses GD or libvips; `SanitizingSvgPurifier`, over `phil/svg-sanitizer`, and
  `SvgMinifier`, which drops comments, `metadata` and whitespace and rounds coordinates; `DoctrineMediaRepository`.
- `MediaType`, the Doctrine column type `phil_media`, and `DoctrineMediaReferences`, which finds the `phil_media`
  columns of the mapped entities and applies each one's deletion behaviour.
- `PhilMediaBundle`, extension alias `phil_media`, keys `storage_dir`, `filesystem`, `secret`, `url_ttl`, `max_pixels`,
  `formats` (a box, or `transformers`, with `quality` and `widths`), `original`, `encoders`, `processor`, `vips_binary`,
  `placeholder`, `svg`, `delete_behaviour` and `admin` (with `attribute`, the role or voter attribute that opens the
  explorer); it wires the container's `clock` service, declares the format `admin_thumbnail`, and prepends the column
  type, the form theme, the explorer's CSRF token among the stateless ones of FrameworkBundle and the AssetMapper path
  when their components are installed; `CsrfProtectionPass`, which stops the container with a message when
  `framework.csrf_protection` is false.
- `MediaFile` and `MediaFileValidator`: a medium that exists, in the allowed collections, of the allowed media types.
- `MediaController` and its routes, with a required `ClockInterface`, which show in the browser only the types listed
  in `MediaController::INLINE_TYPES`, send any other file as an `application/octet-stream` attachment, forbid type
  sniffing, sandbox every response but a PDF, keep a URL that expires no longer than it lasts, and serve a format's
  narrower widths at `{format}@{width}.{type}`; an SVG image purified again the first time it is served, as an `image/svg+xml`
  attachment, when the library has an SVG purifier; with `phil/browser-security-bundle`, every response under its
  policy of user content, which decides what is shown inline.
- `MediaExtension`, with a required `ClockInterface`, `media_url()`, whose expiry is rounded up to a step so that
  pages share one URL, and `media_schema()`.
- `ResponsiveImages`, `ImageCandidates` and the Twig components `PhilMedia:Img` and `PhilMedia:Picture`: width and
  height, `loading="lazy"`, `decoding="async"`, a `srcset` over the format's narrower widths, AVIF and WebP sources and
  a PNG or JPEG fallback; `priority`, which loads eagerly with `fetchpriority="high"`, leaves the decoding to the
  browser and, with `symfony/web-link`, preloads the image or the first source; and the medium's tiny preview with its
  focal point in `data-` values for the `placeholder` Stimulus controller, which paints it through the CSSOM, without a
  `style` attribute, unless `placeholder` is false.
- `PlaceholdersCommand`, `phil:media:placeholders`: a tiny preview for every raster image kept without one, collection
  by collection, a page at a time, with `--limit` and `--dry-run`.
- `MediaAdminController` and `config/admin_routes.php`: the file explorer, as a grid or a list, a page at a time, with
  uploads of several files, collections, renaming, moving and deleting; the same listing as a picker.
  `ExplorerAccess`: every listing and change granted by the application's security on the collection or the medium,
  and a move on its destination too,
  every form checked for this site's origin and Symfony's stateless CSRF token `phil_media_explorer`.
  `MediaPickerType` and its form theme. The Stimulus controllers `explorer`, `upload`, `picker` and `placeholder` in
  `assets/`, mapped for AssetMapper, the first two sending a new CSRF token with each form; the modules of the
  double-submitted token and of the preview, and their tests under Node's test runner.
- `MediaField` and `MediaFieldConfigurator`: a medium in an EasyAdmin CRUD controller, the picker in its form; wired
  only with EasyAdmin. `MediaAdmin::menuItem()`, the link of an EasyAdmin menu to the file explorer, and
  `MediaAdminController::INDEX_ROUTE`.
- `MediaDataCollector`, `MediaTrace`, `MediaReport`, `TraceableMediaStorage` and `TraceableImageProcessor`: the media
  panel of the profiler, in debug.
- The fuzz target of the SVG purifier; the benchmarks `BatchReadBench` and `VipsRenderBench`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
