# Image

An image of the content, in two components under the prop names of Symfony UX Image: `Image`, one `img`, and
`Picture`, its sources in the order given, AVIF first, before that `img`. No JavaScript, no inline style: the sources
are given whole, already produced. Two Twig functions of the bundle of `phil/ux-kit` serve them: `image_origin()`,
which keeps the images of other hosts off the page unless their host is listed, and `image_sources()`, which reads the
files of an image among the assets of AssetMapper.

```twig {"preview":true}
<twig:Picture
    src="/images/bridge.jpg"
    :sources="[{type: 'image/avif', srcset: '/images/bridge.avif'}, {type: 'image/webp', srcset: '/images/bridge.webp'}]"
    alt="A bridge at dusk"
    :width="800"
    :height="600"
/>
```

## Installation

::: installation

The recipe needs `phil/ux-kit`, whose bundle Symfony Flex registers, and `symfony/web-link`, which preloads the main
image:

```sh
composer require phil/ux-kit symfony/web-link
```

## Usage

```twig
<twig:Image src="{{ asset('images/bridge.jpg') }}" alt="A bridge at dusk" :width="800" :height="600" />
```

`src`, `alt`, `width` and `height` are required. The image loads lazily and decodes asynchronously.

## Examples

### AVIF, then WebP, then the fallback

The browser takes the first source whose type it reads, then the `src` of the `img`.

```twig
<twig:Picture
    src="{{ asset('images/bridge.jpg') }}"
    :sources="[
        {type: 'image/avif', srcset: asset('images/bridge.avif')},
        {type: 'image/webp', srcset: asset('images/bridge.webp')},
    ]"
    alt="A bridge at dusk"
    :width="800"
    :height="600"
/>
```

### By density

A map of densities to URLs becomes a `srcset` of `1x`, `2x` and `3x` candidates, for each source and for the fallback:
the static images of a site, produced once and served by AssetMapper.

```twig
<twig:Picture
    src="{{ asset('images/1x/network.png') }}"
    :srcset="{1: asset('images/1x/network.png'), 2: asset('images/2x/network.png'), 3: asset('images/3x/network.png')}"
    :sources="[
        {type: 'image/avif', srcset: {1: asset('images/1x/network.avif'), 2: asset('images/2x/network.avif')}},
        {type: 'image/webp', srcset: {1: asset('images/1x/network.webp'), 2: asset('images/2x/network.webp')}},
    ]"
    alt="The network"
    :width="300"
    :height="200"
/>
```

### By width

With `sizes`, the maps list widths, `480w` and `960w`; `srcset` also takes its candidates as HTML writes them. While
the image loads lazily, `sizes` is written after `auto,`, so that the browsers that know it pick the candidate from
the laid-out width, and the others read the rest of the list.

```twig
<twig:Image
    src="/images/hall.jpg"
    :srcset="{480: '/images/hall-480.jpg', 960: '/images/hall-960.jpg'}"
    sizes="(min-width: 48rem) 50vw, 100vw"
    alt="The main hall"
    :width="960"
    :height="640"
/>
```

A source takes its own `sizes`, or that of the picture.

### A layout

`layout` says how the image fills its container, and computes `sizes` from `width` when none is given:
`constrained` never grows wider than its width (`(min-width: 960px) 960px, 100vw`), `full-width` spans its container
(`100vw`), `fixed` keeps its width and reads its maps as densities. Without a layout, the image shrinks with its
container and `sizes` is as given.

```twig
<twig:Image
    src="/images/hall.jpg"
    :srcset="{480: '/images/hall-480.jpg', 960: '/images/hall-960.jpg'}"
    layout="constrained"
    alt="The main hall"
    :width="960"
    :height="640"
/>
```

### A ratio, a fit and a crop

`aspect` keeps the image's box to a ratio, `16/9`, `4/3`, `3/2`, `1/1`, `21/9`, `3/4`, `2/3` or `9/16`, the picture
covering it by default; `fit="contain"` shows it whole, and `position` says where it is cropped. All are classes, never
an inline style, so a strict Content Security Policy keeps them.

```twig
<twig:Image src="/images/team.jpg" aspect="1/1" position="top" alt="The team" :width="1200" :height="800" />
```

`aspect="custom"` reads `--phil-image-ratio`, set by a class of the site's stylesheet passed in `class`.

### Art direction

A source with `media` is chosen only where its media query holds: a tight crop on phones, a wide one elsewhere. The
sources with a query come first, in the order of their first query, each group AVIF, then WebP, then the others; then
the sources without a query. Each source takes the `width` and `height` of its crop, so that its space is reserved
too.

```twig
<twig:Picture
    src="/images/crowd-wide.jpg"
    :sources="[
        {type: 'image/avif', srcset: '/images/crowd-wide.avif'},
        {type: 'image/avif', srcset: '/images/crowd-square.avif', media: '(max-width: 40rem)', width: 600, height: 600},
        {type: 'image/webp', srcset: '/images/crowd-square.webp', media: '(max-width: 40rem)', width: 600, height: 600},
    ]"
    alt="The crowd"
    :width="1600"
    :height="900"
/>
```

A `media` holding `<`, `>`, `{`, `}` or `;` leaves its source out. A URL in a `srcset` holds no space, which would end
it: write `%20`.

### The main image of the page

`priority` writes `loading="eager"` and `fetchpriority="high"` and leaves the decoding to the browser, as the prop of
the same name of Symfony UX Image. `loading` and `decoding` still override it. It also preloads the image through
Symfony WebLink: `preload()` adds a `Link: </images/cover.jpg>; rel="preload"; as="image"` header to the response, with
its `imagesrcset`, `imagesizes`, `fetchpriority`, `crossorigin` and `referrerpolicy`, which FrankenPHP can send as an
early hint (103) when the controller asks for it. `preload` sets the preload apart from `priority`: `false` leaves it
out, `true` asks for it without priority.

```twig
<twig:Image src="/images/cover.jpg" alt="The stage" :width="1600" :height="900" :priority="true" />
```

A `Picture` preloads its first source, with its `type`, so that a browser that does not read that type skips the
preload and fetches the fallback as usual. With a `media` query, a dark or a still variant, which only the browser can
choose among, nothing is preloaded: the image is still fetched first.

### Dark and still variants

`dark` is offered where the system is dark, `prefers-color-scheme: dark`; `still`, the still image of an animated one,
where the reader reduces motion, `prefers-reduced-motion: reduce`. Each is a URL, a map of densities, or sources of
their own types, written before the other sources, the still image first.

```twig
<twig:Picture
    src="/images/map.png"
    :dark="[{type: 'image/avif', srcset: '/images/map-dark.avif'}, {type: 'image/png', srcset: '/images/map-dark.png'}]"
    alt="The map of the city"
    :width="800"
    :height="600"
/>
<twig:Picture src="/images/spinning-top.gif" still="/images/spinning-top.png" alt="A spinning top" :width="320" :height="320" />
```

The system's scheme decides, not the `data-theme` a page may set: a site whose theme the reader chooses gives the
logo of its theme through `{theme}`, as the `Logo` recipe does.

### Credit, license and creation

`credit`, `license` with `licenseHref`, and `creation` follow the caption, each in a `small` of its own, and make a
`figure` on their own. The license links to its address with `rel="license"`. `creation` takes a code of the IPTC
Digital Source Type vocabulary, `digitalCapture` for a photograph, `humanEdits`, `compositeWithTrainedAlgorithmicMedia`
for an image edited with generative AI, `trainedAlgorithmicMedia` for one created with it, or an enum whose value is
one, as `phil/creation-disclosure` holds them; it is said in the vocabulary's words, or in those of `creationLabel`,
and written in `data-digital-source-type`.

```twig
<twig:Image
    src="/images/bridge.jpg"
    alt="A bridge at dusk"
    :width="800"
    :height="600"
    caption="The bridge at dusk"
    credit="Photo: Ana Lima"
    license="CC BY 4.0"
    licenseHref="https://creativecommons.org/licenses/by/4.0/"
    creation="digitalCapture"
    creationLabel="{{ 'image.creation.photo'|trans }}"
/>
```

### The files of an image among the assets

`image_sources()` finds the files of one image among the assets of AssetMapper by their names, `logo.avif`,
`logo@2x.avif`, `logo.webp`, `logo.png`, `logo@2x.png`, and gives the `src`, `srcset`, `sources`, `width` and `height`
of a `Picture`, with the versioned URLs of AssetMapper. It converts nothing: the files are those the site produced.

```twig
<twig:Picture {{ ...image_sources('images/logo') }} alt="Studio" />
<twig:Picture {{ ...image_sources('images/hall', {widths: [480, 960]}) }} sizes="100vw" alt="The main hall" />
```

The options are `densities`, beyond `1x`, `[2, 3]` by default; `widths`, which read `hall@480w.avif` and the others
instead; `formats`, the types of the sources in order, `['avif', 'webp']`; and `fallbacks`, the formats of the `img`
tried in order, `['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp']`. The width and height are those of the fallback's file
without suffix, read by PHP, never for SVG. An image without fallback is an error.

### A caption

`caption` puts the image, or the picture, in a `figure` with its `figcaption`, the text escaped. A decorative image,
with an empty `alt`, stays decorative: its caption still shows.

```twig
<twig:Image src="/images/map.png" alt="The map of the city" :width="800" :height="600" caption="Where we meet" />
```

### Other attributes

Of the other attributes, only `id`, `class`, `title`, `crossorigin`, `referrerpolicy`, `itemprop`, `draggable`,
`data-*` and `aria-*` reach the `img`, the `class` added to `isImage`; an event handler, a `style` or any other is
left out. On a `Picture`, they go to its `img` too.

```twig
<twig:Image src="/images/team.jpg" alt="The team" :width="800" :height="533" class="rounded" itemprop="image" />
```

## Images of other hosts

An image of another host is refused unless its host is listed, so that no page calls a third party behind the reader's
back: a relative path, a `data:image/` one and an address of the page's own origin are the page's; any other address,
`//cdn.example.com/a.jpg` included, is read by `phil/url` and allowed when its host is one of the list or under one.

```yaml
# config/packages/phil_ux_kit.yaml
phil_ux_kit:
    image:
        hosts: ['images.example.com']
```

An image of a listed host is fetched with `referrerpolicy="no-referrer"`, unless the page gives another policy; a
`crossorigin` stays the page's choice, as it fails where the host sends no CORS header. A refused `src` renders
nothing; a refused `srcset` is left out whole, as is a source holding a refused address. `allow_localhost: true` lets
`localhost` be listed, in development.

## Security

Every value is escaped. A `javascript:`, `vbscript:` or `data:` URL is refused, in whatever case, after spaces or
control characters, or cut by tabs and line breaks, but a `data:image/` one, which an `img` shows without running
anything. A refused `src` renders nothing; a refused `srcset` is left out whole, as is a source holding a refused URL,
without candidates, or of a type other than `image/*`.

## Accessibility

`alt` is required: it says what the image shows, for those who do not see it. An empty `alt` makes the image
decorative, skipped by screen readers through its implicit role; its `title` and `aria-*` attributes, which would name
it again, are then left out. `width` and `height` are required: the browser reserves the image's space at its ratio
before it loads, and the page does not shift under the reader (Cumulative Layout Shift); `isImage` keeps the ratio
when the container is narrower. Give `priority` to the main image above the fold, never to an image below it. The still variant serves those whom motion
makes ill (WCAG 2.3.3); the creation is said in words, not by an icon alone.
