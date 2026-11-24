# StickyHeader

The header of the page, kept at the top of the screen while the page scrolls, which may slide away while the reader scrolls down.

```twig {"preview":true}
<twig:StickyHeader><a href="/">Home</a></twig:StickyHeader>
```

## Installation

::: installation

## Usage

```twig
<twig:StickyHeader :hideOnScroll="true">
    <a href="/">Home</a>
    <nav aria-label="Main">…</nav>
</twig:StickyHeader>
```

## Accessibility

The scroll padding of the page follows the height of the header, so that anchors and the focus never stop beneath it. Hidden, the header is inert: its links cannot be reached by the keyboard nor read by screen readers. It never slides away where motion is reduced, by the system or by `data-motion="reduce"`, nor while it holds the focus.
