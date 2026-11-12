# Images

How to choose the `layout` and the `sizes` of an image, what the main image of a page needs to be painted early, and
how to move the `img` and `picture` elements a template writes by hand to the `Image` and `Picture` recipes. The props
themselves are described in the [image recipe](../image/README.md).

## Choosing a layout and sizes

The browser picks a candidate of a `srcset` of widths before it lays the page out: `sizes` tells it how wide the image
will be drawn. A `sizes` too wide fetches a file larger than needed; one too narrow fetches a blurred one. `layout`
writes it for the common cases, from `width`.

| The image | `layout` | `sizes` written | `srcset` |
|---|---|---|---|
| Keeps one width on every screen: an avatar, a logo, an icon | `fixed` | none | densities, `{1: …, 2: …}` |
| Grows with its container up to its own width: the image of an article | `constrained` | `(min-width: <width>px) <width>px, 100vw` | widths, up to twice `width` |
| Spans its container on every screen: a banner, a hero | `full-width` | `100vw` | widths, up to the widest screen served |
| Sits in a column of a grid or a gallery | none | yours: `(min-width: 48rem) 33vw, 100vw` | widths |
| Has a single file | none, or `fixed` | none | none |

Choose in this order:

1. **A single width?** `fixed`, and a map of densities for screens of density 2 and more. No `sizes`.
2. **As wide as its container, whatever the screen?** `full-width`. When the container has margins, give `sizes`
   yourself, `calc(100vw - 2rem)`, for a closer fit.
3. **Never wider than its file?** `constrained`, with `width` the width of the largest file.
4. **Inside a grid?** No layout: write `sizes` as the grid does, one condition per breakpoint, widest first, the last
   without condition. `Gallery` passes its own `sizes` to every image.

A few rules hold whatever the case:

- Give `width` and `height` always, the intrinsic size of the file: the browser keeps the space at that ratio before
  the image loads, and the page does not shift. `aspect` crops to another ratio without changing them.
- A lazy image adds `auto, ` before the `sizes` written: where the browser supports it, it measures the box once laid
  out. The main image, eager, keeps the `sizes` as written.
- Candidates 1.5 to 2 times apart are enough; more files only fill the cache of the server.
- `sizes` is in CSS pixels or viewport units, never in per cent of the container.

## Checklist for the largest contentful paint

The largest contentful paint (LCP) is the moment the largest image or block of text of the first screen is painted. On
most pages it is an image: the hero, the cover of an article, the first photo of a gallery.

- [ ] One image per page, the largest of the first screen, has `priority`. Only one: preloading several shares the
      bandwidth between them.
- [ ] It is not lazy: `priority` writes `loading="eager"`; do not set `loading="lazy"` on it.
- [ ] It is preloaded: `priority` asks WebLink for a `Link` header; check that the response carries it, and, behind
      FrankenPHP, send it as an early hint (103).
- [ ] Its `srcset` and `sizes` are those of the image, so that the preload fetches the very file the page draws.
- [ ] A `Picture` with a `media` query, or a dark or still variant, cannot be preloaded: keep that art direction for
      images further down, or accept a later paint.
- [ ] Its file is AVIF or WebP first, sized for its `sizes`, not the original from the camera.
- [ ] It is served from the site's own host, or a host listed in `phil_ux_kit.image.hosts` and opened early with a
      `preconnect`.
- [ ] No blurred preview over it: `preview` is for the images further down; the main image is painted as it comes.
- [ ] Its `width` and `height` are given, so that it does not move the text when it arrives (cumulative layout shift).
- [ ] The images below the fold stay lazy, and those far below have `deferred`.
- [ ] Measured: the LCP element and its time in the performance panel of the browser, on a throttled connection.

## Migrating images written by hand

`phil:ux-kit:migrate-images` reads the Twig templates of a project, `templates/` by default, and lists each `img` and
`picture` element with the recipe proposed in its place, or what stands in its way. It proposes only what it
understands, and changes nothing until it is asked to.

```sh
php bin/console phil:ux-kit:migrate-images
php bin/console phil:ux-kit:migrate-images templates/page templates/base.html.twig
php bin/console phil:ux-kit:migrate-images --write
```

```text
templates/page/show.html.twig:12 proposed
  - <img src="{{ asset('images/hall.png') }}" alt="The hall" width="960" height="640" loading="lazy">
  + <twig:Image src="{{ asset('images/hall.png') }}" alt="The hall" :width="960" :height="640" />
templates/page/show.html.twig:20 left as it is
  - <img src="{{ asset('images/plan.png') }}" class="w-100">
    The image has no text alternative: write its alt, or alt="" for a decorative image.
    The image has no width: give its size, so that its space is kept while it loads.
    The image has no height: give its size, so that its space is kept while it loads.
1 element(s) proposed, 1 left as they are.
```

What it proposes:

- an `img` whose attributes all have a place in `Image`, with its `src`, its `alt`, even empty, its `width` and
  `height`, numbers or printed by `{{ … }}`; `42px` loses its unit, and says so;
- a `picture` of `source` elements and one such `img`, as a `Picture` whose `sources` are written as Twig
  expressions;
- `loading="lazy"` and `decoding="async"` dropped, being the defaults of the recipe; `fetchpriority="high"` as
  `priority`, which also preloads the image, and `fetchpriority="low"` as `deferred`;
- `class`, `id`, `title`, `crossorigin`, `referrerpolicy`, `itemprop`, `draggable`, `data-*` and `aria-*` passed on
  as they are.

What it leaves, for a person to rewrite: an image without `alt`, `width` or `height`; a size in per cent; an attribute
the recipe has no place for, `style` or an event handler; a tag holding a `{% … %}` block or printing its attributes
whole; a `picture` holding anything else than its sources and one image; a value of a source printed with a string in
double quotes. An image on another host is proposed with a note: its host goes under `phil_ux_kit.image.hosts`, or the
recipe refuses it. Comments, of Twig and of HTML, are skipped.

`--write` rewrites the proposals only, in place, and leaves every other element as it is. Read the proposals first,
then commit before writing, so that the change is one diff to review. The command comes with the bundle of the kit,
in an application that has the Console component.
