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
and `srcset`, `sources`, `position`, `caption`, `credit`, `license`, `licenseHref`, `creation` and `creationLabel` when
it has them. `aspect` and `fit` apply to every image, so that the rows line up; `sizes` says how wide a column is.

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

## Accessibility

The gallery is a list named by `label`, `role="list"` keeping its semantics where the browser drops them from a list
without markers. The columns follow the width of the gallery, not that of the screen, so that a gallery in a narrow
column stays readable. An image refused by the `image` recipe takes no place; without an image, nothing is drawn.
