# ScrollArea

A box whose content scrolls inside it, with thin scroll bars.

```twig {"preview":true}
<twig:ScrollArea label="Release notes" style="block-size: 12rem">
    <p>…</p>
</twig:ScrollArea>
```

## Installation

::: installation

## Usage

```twig
<twig:ScrollArea label="Release notes" style="block-size: 12rem">
    <p>…</p>
</twig:ScrollArea>
```

## Accessibility

With `label`, the box is a named region that the keyboard reaches and scrolls with the arrows (WCAG 2.1.1). Without it, the browsers of today make a scroller without focusable content reachable themselves.
