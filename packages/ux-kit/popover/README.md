# Popover

A panel opened by a button, on the HTML popover API: it opens and closes, Escape and a click outside close it, without JavaScript; the controller places it next to its button.

```twig {"preview":true}
<twig:Popover id="filters">
    <twig:Popover:Trigger>
        <twig:Button variant="outline" {{ ...popover_trigger_attrs }}>Filters</twig:Button>
    </twig:Popover:Trigger>
    <twig:Popover:Content aria-label="Filters">Pick a category.</twig:Popover:Content>
</twig:Popover>
```

## Installation

::: installation

## Usage

```twig
<twig:Popover id="filters">
    <twig:Popover:Trigger>
        <twig:Button variant="outline" {{ ...popover_trigger_attrs }}>Filters</twig:Button>
    </twig:Popover:Trigger>
    <twig:Popover:Content aria-label="Filters">Pick a category.</twig:Popover:Content>
</twig:Popover>
```

## Accessibility

The panel is a `dialog` named by `aria-label`. On opening the focus goes to its first control; Escape closes it and gives the focus back to the button.
