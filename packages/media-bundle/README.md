# phil/media-bundle

**Uploaded files and images, described, versioned and served at signed URLs in the formats you declare.**

phil/media-bundle is a media library for a Symfony application. Files are kept in a directory or on any Flysystem
filesystem, an S3 bucket among them, and described in Doctrine: their versions, their collection, a title, alternative
text and a credit, a license, and what they owe to artificial intelligence. Images are rendered on demand to the formats
the application declares — transformations in a row, cropping around a focal point, resizing, expanding, and narrower
widths for a `srcset` —, never enlarged unless told. They come out as WebP, AVIF, JPEG or PNG, drawn from the original
each time by GD, through `cwebp` and `avifenc` when they are installed, or by libvips. Each version of an image may get
a tiny preview, painted behind the image while it loads by a Stimulus controller, without a `style` attribute that a
strict Content Security Policy would drop, and `phil:media:placeholders` gives one to the images kept before; an SVG
image is purified and made smaller before it is kept, when `phil/svg-sanitizer` is installed, and served as an image.
The `Img` and `Picture` components print them sized, lazy and with a `srcset`, or fetched first and preloaded for the
main image of a page; a file explorer, behind a role or a voter and Symfony's stateless CSRF tokens, uploads, renames, moves
and deletes them, opens as a picker for a form field or an EasyAdmin field, and is linked from an EasyAdmin menu; a
`phil_media` column lets any entity
point at a medium, checked by a constraint. The bundle does not decide how the AI disclosure is shown to visitors, and
deleting a medium removes it for good: moving it to a trash is the application's choice, with `phil/trash-bundle`.

It is for a site where editors upload pictures and documents that pages then show, and where those pages must stay
fast and light on a phone. Replacing a file keeps the medium's
identifier, so every page that shows it shows the new file, while the previous file stays recorded as a version.
Alternative text, credit and license sit on the medium itself, which makes accessible and properly credited images the
easy path. A record that still shows a medium decides what its deletion does: refuse it, lose the medium, or keep
its identifier.

The bundle plugs into Doctrine ORM for its records, into Symfony's routing and HTTP kernel for `MediaController`, and
into Twig for `media_url()`, `media_schema()` and the explorer when Twig is installed, and into Twig components, the
form and validator components, AssetMapper, EasyAdmin and the profiler when they are. Storage and image processing are
ports, `MediaStorage` and `ImageProcessor`, with a directory, a Flysystem, a GD and a libvips adapter. With
`phil/creation-disclosure`, a medium declares how it was made as an IPTC digital source type; with
`phil/browser-security-bundle`, every file is served under its policy of user content. Each URL carries the medium's
version and an HMAC-SHA256 signature of its path, so a response never changes and a browser or a CDN may keep it a year.
With `url_ttl`, the signature also covers the moment the URL expires, and the response is kept no longer. A URL the
application did not hand out answers `404`, and a file that could run a script in the browser, such as an HTML page, is
downloaded rather than shown; a purified SVG image shows in an `<img>`, and is saved when opened directly.

## Status

| Part | State |
|---|---|
| `Media`, `MediaVersion`, `MediaCollection`, `FileInfo`, `AiDisclosure` | Implemented and tested |
| `MediaLibrary`: upload, replace, delete, render; `ImageFormat`, `RenderBox` | Implemented and tested |
| Transformations: thumbnail, crop, resize, widen, heighten, expand; narrower widths | Implemented and tested |
| `FilesystemStorage`, `FlysystemStorage`, `GdImageProcessor`, `BinaryEncoder`, `DoctrineMediaRepository` | Implemented and tested |
| Signed URLs: `UrlSigner`, `MediaController`, `media_url()`, `media_schema()` | Implemented and tested |
| `Img` and `Picture` components, `ResponsiveImages`; `priority`, preloaded with `symfony/web-link` | Implemented and tested |
| The `phil_media` column type, `MediaFile`, deletion behaviours | Implemented and tested |
| The file explorer, `MediaPickerType`, the Stimulus controllers | Implemented and tested; the controllers checked by hand |
| The EasyAdmin field, `MediaField`; the menu link to the explorer, `MediaAdmin` | Implemented and tested |
| The profiler panel | Implemented and tested |
| `VipsImageProcessor`, `ImageProcessors`: libvips through its command-line tools | Implemented and tested against stand-ins of the tools; rendered by libvips where it is installed |
| Placeholders: a tiny WebP preview per version, the `placeholder` Stimulus controller, `phil:media:placeholders` | Implemented and tested; the controller's painting not checked in a browser |
| SVG images purified and minified: `SanitizingSvgPurifier`, `SvgMinifier`; served as `image/svg+xml` | Implemented and tested |
| The policy of user content of `phil/browser-security-bundle` on every file served | Implemented and tested |
| `MediaRepository::getMany()`: several media in one query | Implemented and tested |
| How a medium was made, as an IPTC digital source type of `phil/creation-disclosure` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, configuration, routes, tables. |
| [The library](doc/library.md) | Uploading, replacing, describing, licensing, collections, deleting. |
| [Formats and URLs](doc/formats.md) | Formats and their transformations, the focal point, rendering and encoders, signed URLs, caching. |
| [Storage](doc/storage.md) | The directory, Flysystem filesystems — local, S3 —, a storage of the application's own. |
| [Responsive images](doc/images.md) | The `Img` and `Picture` components: sizes, `srcset`, file types, fallbacks, priority, placeholders. |
| [Records that show media](doc/doctrine.md) | The `phil_media` column type, the `MediaFile` constraint, what a deletion does. |
| [The file explorer](doc/admin.md) | Routes, listing, uploads, the Stimulus controllers, the picker and its form field. |
| [EasyAdmin](doc/easyadmin.md) | `MediaField`: a medium in a CRUD controller, with the picker in its form; the explorer in the menu. |
| [The profiler panel](doc/profiler.md) | What a request rendered and read, in debug. |
| [Exceptions](doc/exceptions.md) | What the bundle throws, and when. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```php
$media = $library->upload($uploadedFile->getPathname(), $uploadedFile->getClientOriginalName(), $collection);
$media->describe('The party', alt: 'People dancing under paper lanterns', credit: 'Ada');
$media->license('CC-BY-4.0', AiDisclosure::Assisted);
$media->focus(0.3, 0.4);
$repository->save($media);
```

```twig
<twig:PhilMedia:Picture :media="post.cover" format="card" sizes="(min-width: 60em) 50vw, 100vw"/>
<twig:PhilMedia:Img :media="page.hero" format="hero" sizes="100vw" priority/>
<img src="{{ media_url(media, 'thumbnail', 'avif') }}" alt="{{ media.alt }}">
```

## Requirements

PHP 8.5 or later with `ext-fileinfo`, `ext-gd`, `ext-mbstring` and `ext-sodium`; Doctrine ORM 3.7; Flysystem 3;
Symfony 8.2's console, dependency injection, config, HTTP foundation and kernel, process, routing, service contracts
and UID; PSR-20's clock. Twig, Twig components, the form and validator components, AssetMapper, WebLink, EasyAdmin,
`phil/browser-security-bundle`, `phil/creation-disclosure`, `phil/svg-sanitizer` and libvips are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
