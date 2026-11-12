# Image

An image of the content, in two components under the prop names of Symfony UX Image: `Image`, one `img`, and
`Picture`, its sources in the order given, AVIF first, before that `img`. No inline style, and no JavaScript but the
small `image-preview` controller of an optional blurred preview: the sources are given whole, already produced. The
Twig functions of the bundle of `phil/ux-kit` serve them: `image_origin()`, which keeps the images of other hosts off
the page unless their host is listed, `image_sources()`, which reads the files of an image among the assets of
AssetMapper, `image_filters()`, which reads the filter sets of LiipImagine, `picture_props()`, which asks a provider of
the application, `image_json_ld()`, which describes an image to search engines, and `image_check()`, which warns in
development of a text alternative or a size left wrong.

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

The recipe needs `phil/ux-kit`, whose bundle Symfony Flex registers, `symfony/web-link`, which preloads the main
image, and the StimulusBundle, which registers the `image-preview` controller it copies:

```sh
composer require phil/ux-kit symfony/web-link symfony/stimulus-bundle
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

Which layout and which `sizes` to choose, case by case, is in [the guide of the images](../doc/images.md), with the
checklist of the main image of a page.

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

### Where colours are forced

`forced` is offered first, before the still image and the dark variant, where the reader's system forces its colours,
`forced-colors: active`, as Windows does in its contrast themes. The browser repaints the page in the system's colours
but never an image: a diagram drawn in dark lines on a transparent ground vanishes on a dark system background. Its
variant, drawn to read on both, takes the same forms as `dark` and `still`.

```twig
<twig:Picture src="/images/network.png" forced="/images/network-outlined.png" alt="The network" :width="800" :height="600" />
```

An SVG drawn in `currentColor` needs no variant: it takes the colour of the system's text.

### An image far below the fold

`deferred` writes `fetchpriority="low"`: the browser fetches the image after the other resources of the page. It
stays lazy. Give it to the images known to lie far below the fold, a footer's or the end of an article's; `priority`
wins over it.

```twig
<twig:Image src="/images/sponsors.png" alt="Our sponsors" :width="1200" :height="300" :deferred="true" />
```

### A placeholder colour

`placeholder` paints the image's box while it loads, in a quiet tone of the tokens of phil-ux, near the dominant
colour of the picture: `neutral`, `brand`, `red`, `green`, `amber`, `blue` or `violet`, each a class, `hasPlaceholderBlue`
and the others, that follows the clear and the dark scheme. `custom` reads `--phil-image-placeholder`, set by a class
of the site's stylesheet passed in `class`, never by an inline style.

```twig
<twig:Image src="/images/lake.jpg" placeholder="blue" alt="The lake at noon" :width="1600" :height="900" />
```

The tone stays behind a transparent picture once it has loaded: give it to photographs, not to logos or diagrams.

### A blurred preview

`preview` takes a tiny version of the image, a few pixels written inline, `data:image/webp;base64,…`, in PNG, JPEG,
GIF, WebP or AVIF, which the site produces once with the image. The `image-preview` controller the recipe copies
paints it blurred behind the image until the image has loaded or failed, then takes it away; an image already loaded is
left alone. Any other value, an address or an inline SVG, is left out.

```twig
<twig:Image src="/images/lake.jpg" preview="{{ lake.preview }}" alt="The lake at noon" :width="1600" :height="900" />
```

The template writes no style: the image takes the class `hasPreview` and the controller its value, then sets the
background through the CSSOM, which a strict Content Security Policy allows, where a `style` attribute would be dropped;
its `img-src` must allow `data:`, as for the inline images of the recipe. The blur is drawn by an inline SVG at the
ratio of `width` and `height`. Without the controller, nothing is painted.

### A long description

`description` writes a native disclosure after the image, before its caption, for a chart, a diagram or a map whose
text alternative cannot say everything; the image is described by its text through `aria-describedby`, beside any
description the page already gives. Its identifier is that of the image followed by `-description`, or one of its own
when the image has none. `descriptionLabel` is the words of its summary, `Description` by default.

```twig
<twig:Image
    src="/images/visits.png"
    alt="Visits by month in 2026"
    :width="800"
    :height="400"
    id="visits"
    description="{{ 'stats.visits.description'|trans }}"
    descriptionLabel="{{ 'image.description'|trans }}"
/>
```

The description is text, escaped. A decorative image shows it without being described by it.

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

### The filter sets of LiipImagine

`image_filters()` gives the `src`, `srcset`, `sources`, `width` and `height` of a `Picture` from the filter sets of
LiipImagine applied to one image: each set is a candidate of its width, at the address LiipImagine gives it, resized
the first time it is asked for. The sets whose `format` is `avif` or `webp` make the sources, AVIF first; the others,
or WebP when every set writes a modern format, the fallback, whose widest set gives `src`.

```yaml
# config/packages/liip_imagine.yaml
liip_imagine:
    filter_sets:
        hall_480: { filters: { thumbnail: { size: [480, 320], mode: outbound } } }
        hall_960: { filters: { thumbnail: { size: [960, 640], mode: outbound } } }
        hall_960_avif: { format: avif, filters: { thumbnail: { size: [960, 640], mode: outbound } } }
```

```twig
<twig:Picture {{ ...image_filters('uploads/hall.jpg', ['hall_480', 'hall_960', 'hall_960_avif']) }} sizes="100vw" alt="The main hall" />
```

The width of a set is the one its last resizing filter gives, `thumbnail`, `fixed`, `resize`, `scale`, `downscale` or
`relative_resize` with `widen`; a set whose width none of its filters says takes it from the template,
`{hall_wide: 1600}`. The `width` and `height` of the image are given only when the widest set of the fallback crops to
an exact size, a `thumbnail` in `outbound` mode or `fixed`: give them otherwise. An unknown set, or one whose width is
read nowhere, is an error, and so is the function without LiipImagine, which stays optional:

```sh
composer require liip/imagine-bundle
```

The addresses are those of LiipImagine's resolver: those of another host than the page's, a bucket or a CDN, are
refused unless `phil_ux_kit.image.hosts` lists it.

### From an object of the application

`picture_props()` gives the props of a `Picture` for an object the application knows the image of, a medium of a
library or an image of a catalogue, from the first service implementing `Phil\UxKit\Application\Contract\PicturePropsProvider`
that supports it. Autoconfiguration tags such a service `phil_ux_kit.picture_props`.

```php
final readonly class PosterPictures implements PicturePropsProvider
{
    public function supports(object $subject): bool
    {
        return $subject instanceof Poster;
    }

    public function props(object $subject, array $options = []): array
    {
        return ['src' => $subject->url, 'alt' => $subject->title, 'width' => 600, 'height' => 900];
    }
}
```

```twig
<twig:Picture {{ ...picture_props(event.poster) }} :priority="true" />
```

Only the props that describe the image are kept: its files, `src`, `srcset`, `sizes`, `sources`, `dark`, `still` and
`forced`; its size; its text, `alt`, `caption`, `description`, `credit`, `license`, `licenseHref`, `creation` and
`creationLabel`; its box, `aspect`, `fit` and `position`; `placeholder` and `preview`. How it loads and where it stands,
`priority`, `loading`, `layout` and the others, stay the page's choice. The second argument, a map, is handed to the
provider. An object no provider supports is an error.

### Described to search engines

`structured` describes the image as a schema.org `ImageObject`, through `phil/structured-data`: the absolute address of
its file, its `width` and `height`, its `caption`, its `credit` as `creditText`, the address of its license, and its
`creation` as `digitalSourceType`, a member of the schema.org enumeration of the IPTC codes.

```twig
<twig:Image
    src="/images/bridge.jpg"
    alt="A bridge at dusk"
    :width="800"
    :height="600"
    caption="The bridge at dusk"
    credit="Photo: Ana Lima"
    licenseHref="https://creativecommons.org/licenses/by/4.0/"
    creation="digitalCapture"
    :structured="true"
/>
```

When the application gathers the graph of its page through the `NodeCollector` of `phil/structured-data`, the image
joins it and nothing is written here; otherwise a `<script type="application/ld+json">` of its own comes before the
image, a data block that a Content Security Policy does not govern, so it carries no nonce, and whose text cannot close
it. An address is made absolute against the request by Symfony's `UrlHelper`, then read by `phil/url`: an inline
image, or an address it refuses, is described by nothing. A license is described by its address alone; `humanEdits` and
the retired codes, which the enumeration has no member for, say nothing. A `Picture` describes its fallback once.

```sh
composer require phil/structured-data
```

### A caption

`caption` puts the image, or the picture, in a `figure` with its `figcaption`, the text escaped. A decorative image,
with an empty `alt`, stays decorative: its caption still shows.

```twig
<twig:Image src="/images/map.png" alt="The map of the city" :width="800" :height="600" caption="Where we meet" />
```

### A caption over the image

`captionPosition="overlay"` lays the caption, with its credit, its license and its creation, over the foot of the
image, on a veil of `--phil-color-veil` dark enough for its light text whatever the picture; `below`, the default,
keeps it under the image. The figure takes the class `hasOverlayCaption` and is as wide as its image; a description
stays under both. Without a caption, nothing is laid over the image.

```twig
<twig:Image src="/images/hall.jpg" alt="The main hall" :width="960" :height="640" caption="The main hall" captionPosition="overlay" />
```

Keep the caption to a line or two: it covers the foot of the picture.

### A frame

`frame` draws a frame from the tokens of `phil/ux-css`, on the image alone, never on its caption: words separated by
spaces, `rounded` corners of `--phil-image-radius`, a `framed` line of `--phil-image-frame-color` around it, `raised`
over the page by the shadow of `--phil-image-shadow`. Each word is a class, `hasRounded`, `hasFramed`, `hasRaised`; an
unknown word is left out. A class of the site sets the custom properties; nothing is written in a `style` attribute.

```twig
<twig:Image src="/images/team.jpg" alt="The team" :width="800" :height="533" frame="rounded raised" />
```

### A token

`frame="token"` makes of the image a token, a pawn or an avatar: a square box the picture covers, cropped to a disc
inside a ring `--phil-image-token-width` wide, of `--phil-image-token-color`, the loud line of the brand by default.
`position` chooses what the disc keeps of the picture; `raised` lifts it from the page.

```twig
<twig:Image src="/images/pawns/knight.png" alt="The knight" :width="96" :height="96" frame="token raised" position="top" />
```

### Pixel art

`pixelated` enlarges pixel art without blurring it, each pixel drawn as a square of its own colour, through
`image-rendering: pixelated` and the class `hasPixelated`. Give the image its size on the page, a whole multiple of its
own, so that every pixel stays the same size.

```twig
<twig:Image src="/images/map-16.png" alt="The map of the first level" :width="512" :height="512" :pixelated="true" />
```

### A view transition

`transition` names the image in a view transition, so that it moves from one page to the next as one: the class
`hasTransition` and `data-transition`, which the sheet reads through a typed `attr()` into `view-transition-name`,
without a `style` attribute. A name that is no CSS identifier, or a keyword of `view-transition-name`, `none`, `auto`
or `match-element`, is left out. Elsewhere than Chromium 133 and later, the image takes the name of
`--phil-image-transition`, which a class of the site sets, or none, and the page simply changes.

```twig
{# On the list #}
<twig:Image src="/images/posters/42.jpg" alt="The poster of the festival" :width="200" :height="300" transition="poster-42" />

{# On the page of the festival #}
<twig:Image src="/images/posters/42.jpg" alt="The poster of the festival" :width="800" :height="1200" transition="poster-42" :priority="true" />
```

With Turbo 8, a view transition runs between two pages when both say so in their head; Turbo then gives the browser the
new page inside `document.startViewTransition()`:

```twig
<meta name="view-transition" content="same-origin">
```

Without Turbo, a full navigation between two pages of the same origin moves the image too where both stylesheets allow
it, Chromium 126 and later:

```css
@view-transition {
    navigation: auto;
}
```

A name stands once in a page: two images under the same name, on a page of results showing the same poster twice,
skip the whole transition. Name each image after what it shows, `poster-42` rather than `poster`, and only those that
pass from one page to the other. Where motion is reduced, the site's stylesheet shortens or removes the animation of
`::view-transition-group()`.

### On paper

Printed, a figure is never cut between two pages and its caption stays with its image; the image is never taller than
`--phil-image-print-height`, 85% of the page, and shows whole unless it covers its box; a caption laid over the image
prints with its veil. Nothing is to do: the rules are those of `phil/ux-css`, under `@media print`.

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

## Checks in development

While the kernel is in debug, `image_check()` logs a warning on the `phil_ux_kit` channel, which the profiler shows,
for an image whose text alternative is the name of its file, `hall` for `hall.jpg`, `@2x` and the digest of AssetMapper
set aside; a word that says only that it is an image, `image`, `photo`, `logo`, in English or in French; or a text that
starts by saying so, `Photo of the hall`, `Photo de la salle`; and for an image without a width or a height. The page
itself is unchanged; a decorative image is only checked for its size. `phil_ux_kit.image.check` turns the check on or
off whatever the debug.

## Security

Nothing is written in a `style` attribute and nothing runs inline: the image works under a strict Content Security
Policy, `default-src 'self'`, without `'unsafe-inline'` for scripts or styles, images of the site or `data:`. The
browser suite of the kit renders it under such a policy. The blurred preview is painted through the CSSOM, which the
policy allows, as an inline SVG, which `img-src data:` lets through; an image of a listed host needs its host in
`img-src` too, and the JSON-LD script of `structured`, which is data and runs nothing, needs no nonce.

Every value is escaped. A `javascript:`, `vbscript:` or `data:` URL is refused, in whatever case, after spaces or
control characters, or cut by tabs and line breaks, but a `data:image/` one, which an `img` shows without running
anything. A refused `src` renders nothing; a refused `srcset` is left out whole, as is a source holding a refused URL,
without candidates, or of a type other than `image/*`.

## Accessibility

`alt` is required: it says what the image shows, for those who do not see it. An empty `alt` makes the image
decorative, skipped by screen readers through its implicit role; its `title` and `aria-*` attributes, which would name
it again, are then left out. `width` and `height` are required: the browser reserves the image's space at its ratio
before it loads, and the page does not shift under the reader (Cumulative Layout Shift); `isImage` keeps the ratio
when the container is narrower. Give `priority` to the main image above the fold, never to an image below it. The
still variant serves those whom motion makes ill (WCAG 2.3.3); the variant for forced colours, those who need the
contrast of their system (WCAG 1.4.11); the creation is said in words, not by an icon alone. A long description gives
the content of a chart or a diagram in text (WCAG 1.1.1), in a disclosure every reader can open, its summary a prop to
translate. The placeholder and the preview are backgrounds, which screen readers do not see. A caption laid over its
image keeps light text on a veil that reaches 9:1 over a white picture (WCAG 1.4.3).
