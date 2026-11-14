# Carousel

Slides in a row, on the native scroll snapping of the browser: they scroll by touch, trackpad and keyboard without
JavaScript; the controller adds the buttons, the arrows and, on request, the automatic play. Written in Tailwind on
the tokens of the Shadcn kit, its buttons drawn as the kit's buttons, under the props of the PHIL UX kit; it needs no
carousel library.

```twig {"preview":true}
<twig:Carousel label="Photos">
    <twig:Carousel:Content>
        <twig:Carousel:Item>One</twig:Carousel:Item>
        <twig:Carousel:Item>Two</twig:Carousel:Item>
    </twig:Carousel:Content>
    <twig:Carousel:Previous />
    <twig:Carousel:Next />
</twig:Carousel>
```

## Installation

::: installation

## Usage

```twig
<twig:Carousel label="Photos" align="start" :loop="true" :autoplay="5000" pauseLabel="Pause" playLabel="Play">
    <twig:Carousel:Content>
        <twig:Carousel:Item class="[--carousel-item:50%]">One</twig:Carousel:Item>
        <twig:Carousel:Item class="[--carousel-item:50%]">Two</twig:Carousel:Item>
        <twig:Carousel:Item class="[--carousel-item:50%]">Three</twig:Carousel:Item>
    </twig:Carousel:Content>
    <twig:Carousel:Previous text="Previous photo" />
    <twig:Carousel:Next text="Next photo" />
</twig:Carousel>
```

A slide takes the whole width of the viewport; `--carousel-item` sets another width, and `--carousel-size` the height
of a vertical carousel, `24rem` by default. On paper, the slides are printed one after the other, without the buttons.

## Accessibility

A named region, its slides announced as such, the texts as props. Automatic play has a pause button, holds while the
carousel is hovered or focused, and never runs under `prefers-reduced-motion` (WCAG 2.2.2). The arrows follow the
direction of the page.
