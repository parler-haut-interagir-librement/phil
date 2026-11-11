# Illustration

A figure showing one picture chosen among candidates, on the server: random by default, the same for the same seed.
Each candidate is a URL, or a map of `src`, `webp`, `alt`, `width` and `height`.

```twig {"preview":true}
<twig:Illustration :candidates="['/images/main-1.png', '/images/main-2.png']" alt="A building site" />
```

## Installation

::: installation

## Usage

```twig
<twig:Illustration
    :candidates="[{src: asset('images/main.png'), webp: asset('images/main.webp')}]"
    alt="A building site"
/>
```

## Examples

### A drawing of the day

The seed picks the candidate: the day of the year shows a new one each day, the same all day long.

```twig
<twig:Illustration :candidates="drawings" alt="" :seed="'now'|date('z')" />
```

### A caption

```twig
<twig:Illustration candidates="/images/map.png" alt="The map of the city" caption="Where we meet" />
```

## Accessibility

`alt` is required and serves the candidates that have none; it is empty for a decorative picture. The image loads
lazily unless `loading="eager"`. Give each candidate its `width` and `height` to reserve its space.
