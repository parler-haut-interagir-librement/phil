# Figure

Several images under one caption: two moments of an evening, the front and the back of a poster. The images, each an
`Image` or a `Picture` of the `image` recipe, stand in a grid, and one caption, credit and license follow them all.

```twig {"preview":true}
<twig:Figure caption="The opening night" :columns="2">
    <twig:Image src="/images/doors.jpg" alt="The doors open" :width="800" :height="600" />
    <twig:Image src="/images/speech.jpg" alt="The first speech" :width="800" :height="600" />
</twig:Figure>
```

## Installation

::: installation

## Usage

```twig
<twig:Figure caption="{{ 'opening.caption'|trans }}" credit="Photo: Ana Lima">
    <twig:Image src="{{ asset('images/doors.jpg') }}" alt="{{ 'opening.doors'|trans }}" :width="800" :height="600" />
    <twig:Image src="{{ asset('images/guests.jpg') }}" alt="{{ 'opening.guests'|trans }}" :width="800" :height="600" />
    <twig:Image src="{{ asset('images/speech.jpg') }}" alt="{{ 'opening.speech'|trans }}" :width="800" :height="600" />
</twig:Figure>
```

The content is the images, without a caption of their own. `caption`, `credit`, `license` with `licenseHref`,
`creation` and `creationLabel` are those of `Image`, written once under the grid. The grid has as many columns as the
container allows, `--phil-figure-column` wide at least, 10rem by default; `columns`, `2`, `3` or `4`, keeps that number
on every screen. Without a caption, a credit, a license or a creation, the grid stands alone. `id` and `class` reach
the grid.

## Accessibility

Each image keeps its own text alternative, which says what it shows; the caption says what they show together. A
figure with its caption is announced as one, its caption read with it.
