# Formats and URLs

## Formats

A format is a variation of an image the application declares by name: the transformations applied one after the
other, the quality of the lossy file types, from 1 to 100, and the narrower widths it is also rendered at, for a
`srcset`. The name `original` is kept for the file itself, converted whole to another type.

```yaml
phil_media:
    formats:
        thumbnail: { width: 320, height: 320 }                     # a box: one thumbnail transformation, crop
        hero:      { width: 1600, height: 900, mode: fit, quality: 70, widths: [480, 960] }
        card:
            quality: 75
            widths: [320, 640]
            transformers:
                - { type: crop, width: 1200, height: 800 }          # around the focal point
                - { type: widen, width: 960 }
                - { type: expand, width: 960, height: 720, background: '#ffffff' }
    original:
        quality: 80
        widths: [640, 1280]
```

A box, `width` and `height` with an optional `mode`, is a single `thumbnail` transformation. With `transformers`, the
box is ignored and the list is applied in order. Each transformation sees the image as the previous one left it, its
focal point carried along, and every size is 1 to 8000 pixels:

| `type` | Keys | The image |
|---|---|---|
| `thumbnail` | `width`, `height`, `mode` | `crop` covers the box and cuts what overflows around the focal point; `fit` stays inside it whole |
| `crop` | `width`, `height`, `x`, `y` | A part of the image, unscaled, at `x`, `y` or centred on the focal point; never larger than the image |
| `resize` | `width`, `height`, `upscale` | The whole image at this size, its proportions not kept |
| `widen` | `width`, `upscale` | The whole image at this width, its proportions kept |
| `heighten` | `height`, `upscale` | The whole image at this height, its proportions kept |
| `expand` | `width`, `height`, `x`, `y`, `background` | The image on a canvas at least this large, unscaled, at `x`, `y` or centred; the margin transparent, or `#rrggbb` |

No transformation enlarges an image unless it says `upscale: true`: a 40 × 40 image in a 100 × 100 thumbnail comes
out 40 × 40. In PHP, the same formats are `ImageFormat::of()` for a box and `ImageFormat::transforming()` for a list
of `Transform\Transformer`s — `Thumbnail`, `Crop`, `Resize`, `Widen`, `Heighten`, `Expand` —, which
`Transformers::fromDefinition()` also reads from an array. `ImageFormat::apply()` computes the size and focal point a
format gives an image without drawing it.

The `widths` of a format are rendered on request too: `atWidth(480)` is the format followed by a `widen` to 480
pixels, never enlarged. The quality is that of WebP, AVIF and JPEG; PNG is lossless.

## The focal point

```php
$library->focus($media, 0.3, 0.4);   // 0 to 1, across and down; 0.5, 0.5 by default
```

`MediaLibrary::focus()` saves the medium and, when the point moves by a hundredth or more, deletes the renderings
made around the previous one. `Media::focus()` alone only changes the entity: renderings around the old point then
stay in the storage until the medium is deleted.

A `thumbnail` that crops and a `crop` without a position keep the focal point as near their centre as the image
allows; `RenderBox::fill()` computes the part a thumbnail keeps and the size it renders.

## Rendering

`MediaLibrary::rendered($media, 'thumbnail', 'avif')` renders a format on first request and keeps it under
`formats/{id}/{version}/{format}-{x}-{y}.{type}`, where `x` and `y` are the focal point in hundredths: moving the
focal point renders anew. A narrower width is kept as `{format}-{x}-{y}@{width}.{type}`. `MediaController` renders a
missing one when its URL is first requested, so nothing is rendered ahead of time. `MediaLibrary::replace()` deletes
the renderings of the previous version, whose URLs answer `404`, and `MediaLibrary::focus()` those around the
previous focal point. A document that is not an image, a format that is not declared, a width the format does not
list, or a type other than `webp`, `avif`, `jpeg` and `png` is refused.

Every rendering is drawn from the original, never from another rendering: a WebP or an AVIF is not a JPEG compressed
twice. `GdImageProcessor` draws each transformation with GD, transparency kept, and encodes the result. For WebP and
AVIF it hands a lossless PNG to the reference encoders, `cwebp` and `avifenc`, when they are installed: they compress
better than GD at the same quality.

```yaml
phil_media:
    encoders:
        webp: auto      # cwebp when found on the PATH, else GD
        avif: /opt/homebrew/bin/avifenc   # this binary; "gd" for GD
```

`BinaryEncoder` runs the binary without a shell on two files of a private temporary directory, for a minute at most,
and removes them afterwards; an encoder that fails is an `InvalidMedia`, and its URL a `404`.

`GdImageProcessor` reads an image's dimensions before decoding it, and refuses one whose width times height passes
`max_pixels`, 25 million by default: a PNG of a few hundred kilobytes can hold an image whose decoding takes
gigabytes. Such an image is still uploaded and served as it is; its formats answer `404`.

### Rendering with libvips

```yaml
phil_media:
    processor: auto        # libvips when found on the PATH, else GD; "vips" requires it; "gd", the default
    vips_binary: ~         # the path of vips, with vipsheader beside it; ~, searched on the PATH
```

`VipsImageProcessor` renders with libvips through its command-line tools, `vips` and `vipsheader`, along the same steps
as GD: libvips streams an image in tiles instead of decoding it whole, so a large photo takes a fraction of the memory.
Each step is a crop (`extract_area`), a scale (`resize`) and a placement on a canvas (`embed`), on files in the native
format of libvips in a private temporary directory, removed afterwards; the result is saved at the format's quality,
without metadata. Only a JPEG, PNG, GIF, WebP or AVIF image that passes `max_pixels` reaches libvips, so that none of
its other loaders reads an upload; each tool runs without a shell, with `VIPS_BLOCK_UNTRUSTED` set, for a minute at
most. With `processor: vips` and no `vips` found, the processor fails on its first use with `InvalidMedia`.

## Signed URLs

```twig
{{ media_url(media) }}                       {# the file itself #}
{{ media_url(media, 'thumbnail') }}          {# WebP #}
{{ media_url(media, 'thumbnail', 'avif') }}
{{ media_url(media, 'thumbnail', 'avif', 480) }}   {# one of the format's narrower widths #}
{{ media_url(media, 'original', 'webp') }}         {# the file itself, converted #}
```

| Route | Path |
|---|---|
| `phil_media_original` | `/{id}/{version}/original` |
| `phil_media_format` | `/{id}/{version}/{format}.{type}` |
| `phil_media_format_width` | `/{id}/{version}/{format}@{width}.{type}` |

A URL carries an HMAC-SHA256 signature of its path and, with `url_ttl`, of the moment it expires. That moment is
rounded up to a step, a tenth of `url_ttl` and an hour at most: every page rendered during one step hands out the
same URL, which a browser or a CDN can then keep, and a URL stays valid from `url_ttl` to `url_ttl` and one step.
`MediaController`
answers `404` to a URL that is not signed, was signed with another secret, has expired, names an old version or an
unknown medium, or asks for a format that is not declared: nobody reaches a file the application did not hand out.

Since the version is in the path, a response never changes: it is sent with
`Cache-Control: public, max-age=31536000, immutable`, so a browser or a CDN keeps it a year. A URL that expires is
kept only until it expires: its `max-age` is the seconds it has left.

The file itself is served under its safe name. A browser shows it only when its type runs no script on the site's
origin — AVIF, GIF, JPEG, PNG, WebP and PDF, listed in `MediaController::INLINE_TYPES` —; any other file, an HTML
page among them, is sent as `application/octet-stream` with `Content-Disposition: attachment`, so it is downloaded.
Every response carries `X-Content-Type-Options: nosniff`, and every one but a PDF
`Content-Security-Policy: sandbox`, which a browser's PDF viewer does not open.

An SVG image is served as `image/svg+xml` when the library purifies SVG images — `phil/svg-sanitizer` installed and
`svg.purify` on, as it is by default —, so that a logo or an illustration shows in an `<img>`. It is purified again
the first time it is served, so that one kept before the purifier was turned on is served as safely as the others, and
the result is kept beside the formats of its version, `formats/{id}/{version}/purified.svg`, then read back: an
illustration of a few thousand paths takes a fraction of a second to purify, once. One the purifier refuses answers
`404`. It is sent with `Content-Disposition: attachment` and the sandbox: in an `<img>`, a
browser never runs a script of an SVG image nor loads what it references, and the attachment is ignored; opened
directly, the file is saved, not shown as a page of the site. Without a purifier, an SVG image is an
`application/octet-stream` attachment like any other file.

### Under the policy of user content

When `phil/browser-security-bundle` is enabled, its policy of user content decides instead, for the file itself and
for its formats. The types it shows inline — by default the raster images, the audio and video of browsers, and plain
text — are shown; every other file, a PDF among them unless the policy lists it, is sent as
`application/octet-stream` and downloaded. A purified SVG image keeps `image/svg+xml`, as an attachment: the policy
never shows an SVG inline. Every response carries the policy's headers: its own
`Content-Security-Policy`, `default-src 'none'; …; sandbox` by default, `X-Content-Type-Options: nosniff`, the
disposition with the safe name, `Cross-Origin-Resource-Policy`, `Referrer-Policy: no-referrer`,
`X-Permitted-Cross-Domain-Policies: none` and `X-Frame-Options: DENY`. The caching headers stay those above. The
policy is configured under `phil_browser_security.user_content`; nothing is to configure in this bundle.

## schema.org

`media_schema(media)` gives the medium as schema.org data — an `ImageObject` for an image, a `MediaObject`
otherwise —, built by `MediaSchema`:

| Property | Value |
|---|---|
| `contentUrl` | the original's signed URL, absolute |
| `name`, `description`, `creditText` | the title (else the file name), the alternative text, the credit |
| `license` | `https://spdx.org/licenses/<id>`; absent when all rights are reserved |
| `encodingFormat`, `width`, `height`, `dateModified` | the media type, the dimensions, the last change |
| `digitalSourceType` | the IPTC code of the AI disclosure: `trainedAlgorithmicMedia` when generated, `compositeWithTrainedAlgorithmicMedia` when assisted; absent otherwise |

```twig
<script type="application/ld+json">{{ media_schema(media)|json_encode(constant('JSON_UNESCAPED_SLASHES') b-or constant('JSON_HEX_TAG'))|raw }}</script>
```
