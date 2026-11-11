# Sheet

A panel sliding from an edge of the screen: a `Dialog` that keeps to one side, with the same controller.

```twig {"preview":true}
<twig:Sheet id="menu" side="left">
    <twig:Sheet:Trigger>
        <twig:Button variant="outline" {{ ...sheet_trigger_attrs }}>Menu</twig:Button>
    </twig:Sheet:Trigger>
    <twig:Sheet:Content>
        <twig:Sheet:Header><twig:Sheet:Title>Menu</twig:Sheet:Title></twig:Sheet:Header>
    </twig:Sheet:Content>
</twig:Sheet>
```

## Installation

::: installation

## Usage

```twig
<twig:Sheet id="menu" side="left">
    <twig:Sheet:Trigger>
        <twig:Button variant="outline" {{ ...sheet_trigger_attrs }}>Menu</twig:Button>
    </twig:Sheet:Trigger>
    <twig:Sheet:Content>
        <twig:Sheet:Header><twig:Sheet:Title>Menu</twig:Sheet:Title></twig:Sheet:Header>
    </twig:Sheet:Content>
</twig:Sheet>
```

## Accessibility

As `Dialog`: modal, named by its title, closed by Escape and by a click on the backdrop, the focus kept inside and given back; the browser's invokers and `closedby` first, the controller where the browser has neither.
