# Themes

## One theme, light and dark

phil-ux has one theme. Each colour role holds its light and its dark tone in one declaration, `light-dark()`, which
the browser resolves from the `color-scheme` of the element that uses the role:

```css
:root {
    color-scheme: light dark;
    --phil-color-surface: light-dark(var(--phil-neutral-99), var(--phil-neutral-10));
}
```

By default the page follows the system, through `prefers-color-scheme`. `data-theme` chooses instead:

| `data-theme` | Scheme |
|---|---|
| none | The system's |
| `light` | Light |
| `default` | Light, the same as `light` |
| `dark` | Dark |

It goes on the root or on any element, which then flips its subtree: a dark band in a light page is a
`<section data-theme="dark">`.

```twig
{% set theme = random(['default', 'dark']) %}
<html lang="{{ app.request.locale }}" data-theme="{{ theme }}">
```

## A site's overrides

A site keeps the theme and overrides tokens in its own stylesheet, outside any layer, colours and dimensions apart:

```css
/* assets/styles/colors.css: the hue of the brand */
:root {
    --phil-brand-40: oklch(0.40 0.09 300);
    --phil-brand-50: oklch(0.50 0.11 300);
    --phil-brand-70: oklch(0.70 0.12 300);
}

/* assets/styles/dimensions.css: a denser interface, rounder corners */
:root {
    --phil-scale-space: 0.875;
    --phil-scale-radius: 1.5;
}
```

Keep each tone at the lightness its number says, and run the [contrast rule](contrast.md) on the overrides.
