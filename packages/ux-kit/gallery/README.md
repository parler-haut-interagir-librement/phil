# Gallery

A gallery: a list of figures in a grid that fills its container, its columns as wide as the container allows, read by a
container query; each image a `Picture` of the `image` recipe, under its caption, credit, license and creation; with
`lightbox`, each opens in a `Lightbox`.

```twig {"preview":true}
<twig:Gallery
    label="The festival"
    aspect="1/1"
    :images="[
        {src: '/images/stage.jpg', alt: 'The stage', width: 800, height: 600, caption: 'Opening night'},
        {src: '/images/crowd.jpg', alt: 'The crowd', width: 800, height: 600},
    ]"
/>
```

## Installation

::: installation

## Usage

```twig
<twig:Gallery label="{{ 'gallery.festival'|trans }}" :images="photos" aspect="1/1" sizes="(min-width: 48rem) 33vw, 100vw" />
```

`label` names the list for screen readers. Each image takes the props of a `Picture`: `src`, `alt`, `width`, `height`,
and `srcset`, `sources`, `position`, `caption`, `credit`, `license`, `licenseHref`, `creation`, `creationLabel`,
`preview`, `description` and `id` when it has them. `aspect`, `fit` and `placeholder` apply to every image, so that the
rows line up; `sizes` says how wide a column is.

## Examples

### Each image enlarged

```twig
<twig:Gallery
    id="festival"
    label="The festival"
    :lightbox="true"
    openLabel="{{ 'image.enlarge'|trans }}"
    closeLabel="{{ 'dialog.close'|trans }}"
    :images="[
        {src: '/images/stage-480.jpg', alt: 'The stage', width: 480, height: 360, full: {src: '/images/stage.jpg', width: 1600, height: 1200}},
    ]"
/>
```

The identifier of each lightbox is made from `id`, `lightbox-festival-1` and the others; give an `id` to each gallery
of a page. `full` is the image the lightbox shows, the thumbnail itself when it has none.

### A long gallery

`deferred` lets the browser skip the rendering of the figures off screen, with `content-visibility: auto`, and fetches
every image after the other resources of the page, with `fetchpriority="low"`. Each `li` then carries a `data-width`
and a `data-height`, the ratio of the gallery's `aspect`, or of the image's `width` and `height`, which keep its place
until it is rendered: no style attribute is written.

```twig
<twig:Gallery label="The archive" :images="archive" aspect="4/3" placeholder="neutral" :deferred="true" />
```

The ratio is read by `attr()` where the browser types it, Chromium 133 and later; elsewhere each figure off screen
holds a square cell `--phil-gallery-column` high until it has been rendered once, after which the browser remembers its
size. Neither counts the caption, which shows when the figure is rendered, so the scroll bar may still move a little.
With `aspect="custom"`, which no attribute can say, the square cell serves everywhere. Leave `deferred` off for a short
gallery, or for one at the top of the page, whose images should come first.

### A carousel

`layout="carousel"` lays the figures in a row that scrolls sideways and snaps on each, `scroll-snap`, without a script:
by touch, by the trackpad, and by the arrows once the list has the focus, which its `tabindex="0"` gives it. Where the
browser draws them, Chromium 135 and later, `::scroll-marker` adds a marker per figure under the row, named by the text
alternative of its image or its rank, and `::scroll-button()` a button on each side, named by `previousLabel` and
`nextLabel`, which the list carries in `data-previous` and `data-next`. Elsewhere the row scrolls alone, its scroll bar
showing that there is more.

```twig
<twig:Gallery
    label="The strip"
    layout="carousel"
    previousLabel="{{ 'carousel.previous'|trans }}"
    nextLabel="{{ 'carousel.next'|trans }}"
    :images="photos"
    aspect="1/1"
/>
```

The `Carousel` recipe of the Shadcn kit stays the carousel of any content, its buttons drawn by its controller in every
browser; this layout is that of images, without a controller.

### Captions over the images

`captionPosition="overlay"` lays the caption of each image over its foot, as for `Image`; a lightbox keeps its own under
its thumbnail.

## Accessibility

The gallery is a list named by `label`, `role="list"` keeping its semantics where the browser drops them from a list
without markers. The columns follow the width of the gallery, not that of the screen, so that a gallery in a narrow
column stays readable. An image refused by the `image` recipe takes no place; without an image, nothing is drawn. A
figure skipped by a deferred gallery stays in the accessibility tree and is found by the search of the page; its cell
reaches into the gap by the width of the focus ring, which the paint containment of `content-visibility` would
otherwise clip. A `description` follows its image, and in a lightbox its thumbnail, whose button it describes. A
carousel never moves on its own; its markers and buttons are those of the browser, reached by the keyboard, and a
browser without them still scrolls the row at the arrows.
