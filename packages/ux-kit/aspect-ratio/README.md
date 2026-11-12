# AspectRatio

A box kept to a ratio, its picture or video covering it.

```twig {"preview":true}
<twig:AspectRatio ratio="16 / 9">
    <img src="/images/cover.jpg" alt="The hall, seen from the stage">
</twig:AspectRatio>
```

## Installation

::: installation

## Usage

```twig
<twig:AspectRatio ratio="16 / 9">
    <img src="/images/cover.jpg" alt="The hall, seen from the stage">
</twig:AspectRatio>
```

## Accessibility

The box adds nothing for screen readers: the picture inside keeps its own text alternative. A ratio that is not a number or a quotient is left out, for a square.

## Security

No `style` attribute, so a strict Content Security Policy keeps the ratio: a common one, `16 / 9`, `4 / 3`, `3 / 2`, `1`, `21 / 9`, `3 / 4`, `2 / 3` or `9 / 16`, is a class; any other is written in `data-ratio-width` and `data-ratio-height`, which phil-ux reads through a typed `attr()` where the browser reads one, Chromium 133 and later. Elsewhere such a ratio falls back on `--phil-aspect-ratio`, set by a class of the site, else a square.
