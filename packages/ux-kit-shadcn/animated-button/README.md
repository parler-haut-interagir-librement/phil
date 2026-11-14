# AnimatedButton

A button, or a link when it has an address, that moves under the pointer and the focus: a light sweeping across it, a lift or its arrow pushed forward. Pure CSS.

```twig {"preview":true}
<twig:AnimatedButton effect="arrow">Get started</twig:AnimatedButton>
```

## Installation

::: installation

## Usage

```twig
<twig:AnimatedButton effect="arrow">Get started</twig:AnimatedButton>
```

## Accessibility

The animation adds nothing to what the button says, and it stands still under `prefers-reduced-motion`. While `loading`, the button has `aria-busy="true"`; the arrow is hidden from screen readers and turns around right to left.
