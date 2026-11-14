# ProgressRing

Progress as a ring, with the percentage in its centre. Pure CSS and SVG.

```twig {"preview":true}
<twig:ProgressRing label="Upload" :value="40" />
```

## Installation

::: installation

## Usage

```twig
<twig:ProgressRing label="Upload" :value="40" />
```

## Accessibility

A `progressbar` with its numbers; the ring and its text are hidden from screen readers. Without a value, no number is claimed and the ring turns, standing still under reduced motion. The ring runs the other way right to left.
