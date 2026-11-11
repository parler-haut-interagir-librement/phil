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

The box adds nothing for screen readers: the picture inside keeps its own text alternative. A ratio that is not a number or a quotient is left out, for a square, so no other style can reach the attribute.
