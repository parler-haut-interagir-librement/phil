# Progress

The progress of a task, on the native `progress` element: a value between zero and the maximum, or none while the end is unknown.

```twig {"preview":true}
<twig:Progress :value="64" aria-label="Upload of the photos" />
```

## Installation

::: installation

## Usage

```twig
<twig:Progress :value="64" aria-label="Upload of the photos" />
```

## Accessibility

The browser announces the value as a percentage, and a bar without a value as busy. Name it with `aria-label` or a `Label`. phil-ux stills the moving bar under `prefers-reduced-motion`.
