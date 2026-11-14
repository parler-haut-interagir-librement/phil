# Sparkline

A small line of values, drawn by the server.

```twig {"preview":true}
<twig:Sparkline label="Rising, from 2 to 9" :values="[2, 4, 3, 9]" :area="true" />
```

## Installation

::: installation

## Usage

```twig
<twig:Sparkline label="Rising, from 2 to 9" :values="[2, 4, 3, 9]" :area="true" />
```

## Accessibility

One image, whose `label` says what the line shows: the shape alone says nothing to a screen reader, so write the conclusion there.
