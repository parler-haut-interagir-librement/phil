# Testing

| Area | What is covered |
|---|---|
| `FileInfo`, `Media`, `MediaVersion`, `MediaCollection` | Every check and bound; versions; descriptions, licenses, focal points; the tables and columns |
| `ImageFormat`, `RenderBox`, `UrlSigner` | Every rule; crops and fits, rounding, never enlarging; signatures and expiry |
| The transformations, `Transformers` | Each step, the focal point carried through, rounding, `upscale`, positions and backgrounds, every definition refused |
| `MediaLibrary`, `DoctrineMediaRepository` | Uploads, replacements, deletions that leave other media alone, renderings kept, collections a page at a time |
| `FilesystemStorage`, `FlysystemStorage` | Keys that stay in the directory or the filesystem; Flysystem's failures |
| `GdImageProcessor`, `BinaryEncoder` | The focal point in view, transparency, each file type, transformations in a row; the encoders' arguments, private directory and clean-up, through a stand-in binary, and `cwebp` and `avifenc` themselves when they are installed |
| `MediaController`, `MediaExtension` | URLs signed and verified, narrower widths, expiry, `404`s, cache headers |
| `MediaId`, `MediaType`, `DoctrineMediaReferences` | Identifiers refused; the column read and written; every column found, embeddables and mapped superclasses included, counted, emptied; each behaviour of a deletion |
| `MediaFile` | Each violation and its code, patterns, the medium outside every collection, values refused |
| `ResponsiveImages`, `Img`, `Picture` | Sizes from the focal point, the `srcset`, fallbacks, media that cannot be rendered or no longer exist |
| `MediaAdminController`, `ExplorerAccess`, `MediaPickerType` | Grid, list, pages, picker; uploads several at once, too large, refused; collections, renaming, moving, deleting, a medium in use; forms from another site, without their CSRF token or with a wrong one; every listing and change refused without the attribute, the subject asked about; the field's data and widget |
| `MediaField`, `MediaFieldConfigurator` | The field's options, the medium found from each kind of value, the collection, the template with and without a medium |
| The profiler | The trace of each operation, hits, the panel rendered over a stand-in profiler layout |
| `PhilMediaBundle` | Services, defaults, settings refused, debug, the prepended Doctrine, Twig and AssetMapper settings |
| `VipsImageProcessor`, `ImageProcessors` | The commands given to libvips for each step, alpha and JPEG, quality and metadata, what never reaches it, failures and clean-up, through stand-ins of `vips` and `vipsheader`; libvips itself when it is installed |
| Placeholders | One per version, computed once, none without a format, for a file that is not a raster image, that cannot be rendered or whose preview is too large; the preview and its position handed to the components, never a `style`; `phil:media:placeholders` over several pages and collections, its limit, dry run, failures and refusals |
| `priority` | `loading`, `decoding` and `fetchpriority`, what an explicit value keeps; the preload link of an `Img`, of a `Picture`'s first source and of a file that cannot be rendered, beside the links already there, nothing without a request or a provider that evolves |
| `SanitizingSvgPurifier`, `SvgMinifier`, SVG uploads | What is dropped, text kept, every rounding case, refusals; an SVG image served purified as `image/svg+xml`, one kept unpurified purified when first served, then read back, anew for a new version, one refused a `404`, an octet stream without a purifier |
| The policy of user content | The headers of the original and of a format, what is shown inline under the default and a custom policy |
| `getMany()` | The order asked, duplicates and unknown identifiers, one query per 500 |
| How a medium was made | The AI disclosure from each kind of digital source type, retired types, schema.org |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, Twig and Twig components: an image is
uploaded, its `media_url()`s are rendered by Twig and requested through the kernel, the `Img` and `Picture`
components are rendered, and the explorer is listed, renamed through — refused without a token, accepted with a token
and its cookie, which the answer clears — and opened as a picker. In its environment `user_content`, the kernel adds
`phil/browser-security-bundle` and the placeholders: files come back under the policy of user content, an SVG
purified and served as `image/svg+xml`, the components with their preview for the `placeholder` controller and the
image's own controllers kept; the test is skipped without the bundle. The components with `priority` are rendered too.

The fuzz target `tests/Fuzz/svg-purifier.php` feeds the SVG purifier with what nobody wrote: it returns an SVG element
that comes back the same when purified again, or throws `InvalidMedia`, and nothing else.

`tests/js` runs the controllers' pure modules under Node's test runner: file sizes, the double-submitted CSRF token,
and the preview and position the `placeholder` controller accepts. The explorer's three controllers are checked by
hand in a browser: switching the view keeps it after a reload; dropping three files shows three progress bars, then
the listing with them; a file over `max_upload_bytes` is refused before it is sent; the picker opens in a dialog,
follows a collection link inside it, and fills the field. How the `placeholder` controller paints and clears the
preview in a browser has no automated test.

```sh
vendor/bin/phpunit packages/media-bundle/tests
node --test tests/js     # from packages/media-bundle
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. From the root of the monorepo:

```sh
composer bench -- packages/media-bundle/tests/Benchmark
```

`BatchReadBench` compares one `get()` per medium with one `getMany()` for 12, 48 and 200 media; `VipsRenderBench`
compares GD and libvips on a thumbnail of a 1920 × 1080 and a 6000 × 4000 photo, and measures nothing for libvips when
it is not installed.
