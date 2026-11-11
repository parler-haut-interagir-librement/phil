# DropdownMenu

A menu of actions opened by a button, on the popover API: Escape and a click outside close it and the focus goes back to the button.

```twig {"preview":true}
<twig:DropdownMenu id="actions">
    <twig:DropdownMenu:Trigger>
        <twig:Button variant="outline" {{ ...dropdown_menu_trigger_attrs }}>Actions</twig:Button>
    </twig:DropdownMenu:Trigger>
    <twig:DropdownMenu:Content>
        <twig:DropdownMenu:Item as="a" href="/edit">Edit</twig:DropdownMenu:Item>
        <twig:DropdownMenu:Separator />
        <twig:DropdownMenu:Item variant="destructive">Delete</twig:DropdownMenu:Item>
    </twig:DropdownMenu:Content>
</twig:DropdownMenu>
```

## Installation

::: installation

## Usage

```twig
<twig:DropdownMenu id="actions">
    <twig:DropdownMenu:Trigger>
        <twig:Button variant="outline" {{ ...dropdown_menu_trigger_attrs }}>Actions</twig:Button>
    </twig:DropdownMenu:Trigger>
    <twig:DropdownMenu:Content>
        <twig:DropdownMenu:Item as="a" href="/edit">Edit</twig:DropdownMenu:Item>
        <twig:DropdownMenu:Separator />
        <twig:DropdownMenu:Item variant="destructive">Delete</twig:DropdownMenu:Item>
    </twig:DropdownMenu:Content>
</twig:DropdownMenu>
```

## Accessibility

The menu of WAI-ARIA: the arrows, Home, End and the first letters move, Enter and Space choose, the right arrow opens a sub-menu and the left arrow closes it.
