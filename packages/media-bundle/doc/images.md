# Responsive images

With `symfony/ux-twig-component`, the bundle registers two Twig components that print a medium in a format, sized
before the image arrives and offered at the format's narrower widths.

## `Img`

```twig
<twig:PhilMedia:Img :media="post.cover" format="card" sizes="(min-width: 60em) 50vw, 100vw" class="cover"/>
```

```html
<img src="/media/…/card.webp?signature=…"
     srcset="/media/…/card@320.webp?signature=… 320w, /media/…/card.webp?signature=… 640w" sizes="(min-width: 60em) 50vw, 100vw"
     width="640" height="427" alt="People dancing under paper lanterns" loading="lazy" decoding="async" class="cover">
```

| Property | Default | |
|---|---|---|
| `media` | — | A `Media`, a `MediaId` — what a `phil_media` column holds — or the identifier's text |
| `format` | `original` | A declared format, or the file itself converted |
| `type` | `webp` | `webp`, `avif`, `jpeg` or `png` |
| `alt` | the medium's alternative text | `''` for a decorative image |
| `sizes` | none | Printed with the `srcset` |
| `priority` | `false` | `true` for the main image above the fold — see [Priority](#priority) |
| `loading` | `lazy`, `eager` with `priority` | `lazy` or `eager` |
| `decoding` | `async`, none with `priority` | `async`, `sync` or `auto` |
| `placeholder` | `true` | `false` to leave out the medium's tiny preview |

Any other attribute, `class` above, is printed on the `<img>` as it is. A `data-controller` of its own is kept beside
the bundle's `placeholder` controller.

## `Picture`

```twig
<twig:PhilMedia:Picture :media="post.cover" format="card" sizes="100vw"/>
```

```html
<picture>
    <source type="image/avif" srcset="…/card@320.avif?… 320w, …/card.avif?… 640w" sizes="100vw">
    <source type="image/webp" srcset="…/card@320.webp?… 320w, …/card.webp?… 640w" sizes="100vw">
    <img src="…/card.jpeg?…" srcset="…" sizes="100vw" width="640" height="427" alt="…" loading="lazy" decoding="async">
</picture>
```

The `<source>`s offer the `types`, `['avif', 'webp']` by default, in order; the `<img>` falls back to PNG for an
original that may be transparent — PNG, GIF, WebP, AVIF — and to JPEG otherwise. The other properties are those of
`Img`.

## Priority

```twig
<twig:PhilMedia:Picture :media="page.hero" format="hero" sizes="100vw" priority/>
```

The image a visitor sees first, above the fold, is the one the browser should fetch first. With `priority`, the
`<img>` is loaded eagerly and fetched before the others, and its decoding is left to the browser:

```html
<img src="…/hero.jpeg?…" … loading="eager" fetchpriority="high">
```

With `symfony/web-link`, the image is also preloaded: the response carries
`Link: <…/hero.avif?…>; rel="preload"; as="image"; fetchpriority="high"`, with `imagesrcset` and `imagesizes` when the
image has a `srcset`, and the `type` of the first `<source>` of a `Picture` — the one a browser that reads AVIF takes.
The web server may send that header early as a `103 Early Hints`. A `loading` or a `decoding` given beside `priority`
wins over it. Give `priority` to one image a page, two at most: an image that is fetched first delays the others.

## What is printed

`ResponsiveImages` computes, without rendering anything, the size the format gives the original — its
transformations applied to the original's size and focal point by `ImageFormat::apply()` — and lists in the `srcset`
the format's narrower widths smaller than that size, then the format itself. A format with no narrower width has no
`srcset`. The URLs are those of `media_url()`, signed and expiring as configured, and the files are rendered when a
browser first asks for them.

A medium that cannot be rendered — a PDF, an SVG, an image whose size is unknown — is printed as its file, without a
size or a `srcset`, and a `Picture` then has no `<source>`. A purified SVG image shows there as an image: see
[Formats and URLs](formats.md#signed-urls). A medium that no longer exists prints nothing: a record
whose column was left with `keep` does not break its page.

## Placeholders

```yaml
phil_media:
    placeholder:
        enabled: true
        width: 16      # the box the preview fits in, 4 to 64 pixels
        quality: 40
```

With `placeholder` enabled, each version of a JPEG, PNG, GIF, WebP or AVIF image gets a tiny preview: the whole image
fitted in a box of `width` pixels, encoded as WebP by the image processor, and kept on the medium as a `data:` URL of
4,096 characters at most, a few hundred in practice. It is computed once, when the file is uploaded or replaced; a new
version empties it and computes it again. An image that cannot be rendered, or whose preview comes out larger, has
none, and its upload still succeeds.

Media kept before the option was enabled get theirs from the command, never from a page:

```sh
bin/console phil:media:placeholders              # every image without a preview
bin/console phil:media:placeholders --limit=500  # at most 500 in this run
bin/console phil:media:placeholders --dry-run    # how many are missing
```

It reads the media collection by collection, a page of 100 at a time, and gives a preview to each JPEG, PNG, GIF, WebP
or AVIF image of known size that has none, through `MediaLibrary::placeholder($media)`; an image that cannot be
rendered is counted and left without one. Run again, it renders only what is still missing, so a large library can be
filled in several runs. It fails when the placeholders are off.

The `Img` and `Picture` components hand the preview to the bundle's `placeholder` Stimulus controller, with the focal
point, and write no `style` attribute:

```html
<img src="…/card.webp?…" width="640" height="427" alt="…" loading="lazy" decoding="async"
     data-controller="phil--media-bundle--placeholder"
     data-phil--media-bundle--placeholder-src-value="data:image/webp;base64,UklGR…"
     data-phil--media-bundle--placeholder-position-value="30% 40%">
```

The controller paints the preview behind the image through the CSSOM — covering the image's box, centred on the focal
point —, and takes it away once the image has loaded or failed; an image already in the cache is left alone. The
default `Content-Security-Policy` of `phil/browser-security-bundle`, whose `style-src` refuses inline styles, allows
it, where a `style` attribute would be dropped. The controller paints only a WebP, AVIF, JPEG or PNG image inline in
base 64, of 4,096 characters at most, at a position in whole percents; any other value is ignored. Without JavaScript,
the page shows the images as they arrive. `:placeholder="false"` leaves the preview out.
