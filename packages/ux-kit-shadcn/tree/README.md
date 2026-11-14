# Tree

A tree of items, on the WAI-ARIA pattern.

```twig {"preview":true}
<twig:Tree label="Files">
    <twig:Tree:Item label="Documents" :branch="true" :expanded="true">
        <twig:Tree:Item label="Report" href="/report" />
    </twig:Tree:Item>
    <twig:Tree:Item label="Photos" />
</twig:Tree>
```

## Installation

::: installation

## Usage

```twig
<twig:Tree label="Files">
    <twig:Tree:Item label="Documents" :branch="true" :expanded="true">
        <twig:Tree:Item label="Report" href="/report" />
    </twig:Tree:Item>
    <twig:Tree:Item label="Photos" />
</twig:Tree>
```

## Accessibility

One stop of the Tab key, then the arrows, mirrored right to left: down and up from item to item, forward to open or enter, back to close or leave. Home, End and first letters move, Enter and the Space bar choose. Before the script runs, everything it would fold shows. `tree:select` carries the value chosen.
