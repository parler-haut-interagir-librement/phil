# Pagination

The links between the pages of a list.

```twig {"preview":true}
<twig:Pagination>
    <twig:Pagination:Content>
        <twig:Pagination:Item><twig:Pagination:Previous href="?page=1" /></twig:Pagination:Item>
        <twig:Pagination:Item><twig:Pagination:Link href="?page=2" :active="true">2</twig:Pagination:Link></twig:Pagination:Item>
        <twig:Pagination:Item><twig:Pagination:Ellipsis /></twig:Pagination:Item>
        <twig:Pagination:Item><twig:Pagination:Next href="?page=3" /></twig:Pagination:Item>
    </twig:Pagination:Content>
</twig:Pagination>
```

## Installation

::: installation

## Usage

```twig
<twig:Pagination>
    <twig:Pagination:Content>
        <twig:Pagination:Item><twig:Pagination:Previous href="?page=1" /></twig:Pagination:Item>
        <twig:Pagination:Item><twig:Pagination:Link href="?page=2" :active="true">2</twig:Pagination:Link></twig:Pagination:Item>
        <twig:Pagination:Item><twig:Pagination:Ellipsis /></twig:Pagination:Item>
        <twig:Pagination:Item><twig:Pagination:Next href="?page=3" /></twig:Pagination:Item>
    </twig:Pagination:Content>
</twig:Pagination>
```

## Accessibility

A `nav` named by `label`; the current page says `aria-current="page"`. Each text is a prop, to translate.
