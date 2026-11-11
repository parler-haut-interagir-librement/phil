# Carousel

Slides in a row, on native scroll snapping: they scroll by touch, trackpad and keyboard without JavaScript; the controller adds the buttons, the arrows and, on request, the automatic play. `::scroll-button()` being in one engine alone, the buttons do not rely on it.

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
<twig:Carousel label="Photos">
    <twig:Carousel:Content>
        <twig:Carousel:Item>One</twig:Carousel:Item>
        <twig:Carousel:Item>Two</twig:Carousel:Item>
    </twig:Carousel:Content>
    <twig:Carousel:Previous />
    <twig:Carousel:Next />
</twig:Carousel>
```

## Accessibility

A named region, its slides announced as such, the texts as props. Automatic play has a pause button, holds while the carousel is hovered or focused, and never runs under `prefers-reduced-motion` (WCAG 2.2.2).
