# ToggleGroup

Toggles that belong together: radio buttons for one choice, checkboxes for several, submitted with the form, without JavaScript.

```twig {"preview":true}
<twig:ToggleGroup name="align" label="Text alignment" value="left" variant="outline">
    <twig:ToggleGroup:Item value="left">Left</twig:ToggleGroup:Item>
    <twig:ToggleGroup:Item value="center">Centre</twig:ToggleGroup:Item>
    <twig:ToggleGroup:Item value="right">Right</twig:ToggleGroup:Item>
</twig:ToggleGroup>
```

## Installation

::: installation

## Usage

```twig
<twig:ToggleGroup name="align" label="Text alignment" value="left" variant="outline">
    <twig:ToggleGroup:Item value="left">Left</twig:ToggleGroup:Item>
    <twig:ToggleGroup:Item value="center">Centre</twig:ToggleGroup:Item>
    <twig:ToggleGroup:Item value="right">Right</twig:ToggleGroup:Item>
</twig:ToggleGroup>
```

## Accessibility

The group is named by `label`. With `type="single"`, the toggles are radio buttons: one Tab reaches them and the arrows move between them. With `type="multiple"`, they are checkboxes, each reached by Tab, their field name ending in `[]`.
