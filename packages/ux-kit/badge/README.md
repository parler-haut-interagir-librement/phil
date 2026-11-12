# Badge

A short label: a tag, a status, a count.

```twig {"preview":true}
<twig:Badge variant="secondary">Draft</twig:Badge>
```

## Installation

::: installation

## Usage

```twig
<twig:Badge>New</twig:Badge>
<twig:Badge as="a" href="/tags/news" variant="outline">News</twig:Badge>
<twig:Badge color="#7c3aed">Workshop</twig:Badge>
```

## Security

`color` is written in `data-color`, never in a `style` attribute, so a strict Content Security Policy keeps it: phil-ux reads it through a typed `attr()` where the browser reads one, Chromium 133 and later. Elsewhere the badge keeps the colour of its variant; a class of the site setting `--phil-badge-color` works in every browser. A value that is not a colour is left out.
