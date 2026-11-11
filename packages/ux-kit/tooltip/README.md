# Tooltip

A short description of a control, shown on hover and focus: it can be hovered, stays until left, and Escape dismisses it (WCAG 1.4.13).

```twig {"preview":true}
<twig:Tooltip id="save">
    <twig:Tooltip:Trigger>
        <twig:Button {{ ...tooltip_trigger_attrs }}>Save</twig:Button>
    </twig:Tooltip:Trigger>
    <twig:Tooltip:Content>Saves the draft</twig:Tooltip:Content>
</twig:Tooltip>
```

## Installation

::: installation

## Usage

```twig
<twig:Tooltip id="save">
    <twig:Tooltip:Trigger>
        <twig:Button {{ ...tooltip_trigger_attrs }}>Save</twig:Button>
    </twig:Tooltip:Trigger>
    <twig:Tooltip:Content>Saves the draft</twig:Tooltip:Content>
</twig:Tooltip>
```

## Accessibility

The trigger is described by the content through `aria-describedby`, also when the tooltip is hidden. Nothing that matters goes in a tooltip alone: a touch screen has no hover.
