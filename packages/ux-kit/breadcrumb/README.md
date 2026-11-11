# Breadcrumb

Where the page sits in the site.

```twig {"preview":true}
<twig:Breadcrumb>
    <twig:Breadcrumb:List>
        <twig:Breadcrumb:Item><twig:Breadcrumb:Link href="/">Home</twig:Breadcrumb:Link></twig:Breadcrumb:Item>
        <twig:Breadcrumb:Separator />
        <twig:Breadcrumb:Item><twig:Breadcrumb:Page>Events</twig:Breadcrumb:Page></twig:Breadcrumb:Item>
    </twig:Breadcrumb:List>
</twig:Breadcrumb>
```

## Installation

::: installation

## Usage

```twig
<twig:Breadcrumb>
    <twig:Breadcrumb:List>
        <twig:Breadcrumb:Item><twig:Breadcrumb:Link href="/">Home</twig:Breadcrumb:Link></twig:Breadcrumb:Item>
        <twig:Breadcrumb:Separator />
        <twig:Breadcrumb:Item><twig:Breadcrumb:Page>Events</twig:Breadcrumb:Page></twig:Breadcrumb:Item>
    </twig:Breadcrumb:List>
</twig:Breadcrumb>
```

## Accessibility

A `nav` named by `label`, an ordered list; the current page says `aria-current="page"`.
