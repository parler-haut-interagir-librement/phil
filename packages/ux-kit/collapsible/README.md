# Collapsible

A part of the page that opens and closes, on `details` and `summary`, without JavaScript.

```twig {"preview":true}
<twig:Collapsible>
    <twig:Collapsible:Trigger>Three more people starred this project</twig:Collapsible:Trigger>
    <twig:Collapsible:Content>
        <ul><li>Ada</li><li>Grace</li><li>Alan</li></ul>
    </twig:Collapsible:Content>
</twig:Collapsible>
```

## Installation

::: installation

## Usage

```twig
<twig:Collapsible>
    <twig:Collapsible:Trigger>Three more people starred this project</twig:Collapsible:Trigger>
    <twig:Collapsible:Content>
        <ul><li>Ada</li><li>Grace</li><li>Alan</li></ul>
    </twig:Collapsible:Content>
</twig:Collapsible>
```

## Accessibility

The summary is the button: the browser says whether it is open, opens it at Enter and at the space bar, and opens it by itself when a search of the page finds a word inside.
