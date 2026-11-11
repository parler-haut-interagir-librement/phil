# Select

A list of options to choose one from, submitted as a field of the form when it has a `name`. For a page without JavaScript, the `select` element is styled by phil-ux on its own.

```twig {"preview":true}
<twig:Select id="fruit" name="fruit">
    <twig:Select:Trigger><twig:Select:Value placeholder="Pick a fruit" /></twig:Select:Trigger>
    <twig:Select:Content>
        <twig:Select:Group id="winter">
            <twig:Select:Label>Winter</twig:Select:Label>
            <twig:Select:Item value="apple">Apple</twig:Select:Item>
            <twig:Select:Item value="pear" :disabled="true">Pear</twig:Select:Item>
        </twig:Select:Group>
    </twig:Select:Content>
</twig:Select>
```

## Installation

::: installation

## Usage

```twig
<twig:Select id="fruit" name="fruit">
    <twig:Select:Trigger><twig:Select:Value placeholder="Pick a fruit" /></twig:Select:Trigger>
    <twig:Select:Content>
        <twig:Select:Group id="winter">
            <twig:Select:Label>Winter</twig:Select:Label>
            <twig:Select:Item value="apple">Apple</twig:Select:Item>
            <twig:Select:Item value="pear" :disabled="true">Pear</twig:Select:Item>
        </twig:Select:Group>
    </twig:Select:Content>
</twig:Select>
```

## Accessibility

The select-only combobox of WAI-ARIA: the arrows, Home, End, Enter and the first letters choose; Escape and a click outside close, on the popover API; a `<label for>` names the trigger.
